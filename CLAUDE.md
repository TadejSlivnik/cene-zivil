# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Cene živil (https://www.cene-zivil.si/) compares grocery prices across Slovenian retailers (DM, Hofer, Lidl, Mercator, Spar, Tuš). Built on Symfony 7.4 LTS and Doctrine ORM 3 / DBAL 4 (PHP >= 8.4; production runs 8.5), with a Webpack Encore 7, Tailwind 4 and Flowbite 4 frontend. User-facing text, routes and meta content are in Slovenian.

## Commands

```sh
composer install && npm install

# Frontend assets (Webpack Encore). public/build/ is not committed: deploy.sh builds it on the server
# (Node 22.18+) into public/build-next and swaps it in. The page tests need a build too.
npm run dev | npm run watch | npm run build

# Database
php bin/console doctrine:migrations:diff        # generate a migration after changing entity mapping attributes
php bin/console doctrine:migrations:migrate     # deploy.sh runs `d:m:m -n`

# Local server
symfony server:start

# Scheduler (replaces cron in production; systemd unit in deploy/cene-zivil-scheduler.service)
php bin/console debug:scheduler                      # list scheduled commands and their next run
php bin/console messenger:consume scheduler_default  # run the scheduler worker

# Data sync (scheduled by src/Scheduler/Schedule.php)
php bin/console app:sync:{dm|hofer|lidl|mercator|spar|tus}
php bin/console app:check:products        # re-checks 10 stale products per run for 404s or price changes
php bin/console app:handle:promotions     # resets price to regularPrice after promotionEndsDate passes
php bin/console app:queue:image [all]     # pulls EANs out of product images with Gemini (Spar only)
php bin/console app:sitemap
php bin/console app:json-output | app:json-output-spar   # export to var/dump/gtin-dump/
```

```sh
composer test      # PHPUnit (needs MariaDB; DATABASE_URL in .env.test, override in .env.test.local)
composer phpstan   # level 5, existing issues in phpstan-baseline.neon
composer cs-fix    # PHP-CS-Fixer @Symfony
composer ci        # validate + lint + cs + phpstan + test, as CI runs them
```

Test layout: `tests/Unit` (helpers, entities, shop parsing against `tests/Fixtures/shops/*`, never the network), `tests/Functional` (controller via WebTestCase), `tests/Integration` (sync commands with shop services replaced in the test container). DAMADoctrineTestBundle rolls back each test's transaction. `tests/Support/ProductFactory` creates products; use `backdate()` for `created_at`/`updated_at`, since Gedmo overwrites them on flush. A service can only be replaced in the test container before it is first used, so tests that run a command twice use one stub with consecutive return values. Functional tests need `public/build` (run `npm run build` first); in CI the frontend job builds it and the PHP job downloads it as an artifact.

## Architecture

**Data flow:** a shop service (`src/Service/*Service.php`, extends `AbstractShopService`) fetches a retailer's API or HTML and normalizes each product into a plain array. A sync command (`src/Command/Sync*Command.php`, extends `AbstractSyncCommand`) passes those arrays to `updateProducts()`, which upserts `Product` rows and adds a `ProductPriceHistory` row whenever the price fields change. `IndexController` renders search results from `ProductRepository::findByTerms()`.

**Normalized item array.** Every service must return items with these keys: `source`, `productId`, `title`, `url`, `price`, `regularPrice`, `discount`, `unit`, `unitPrice`, `unitQuantity`, and optionally `ean`, `eanImage` and `promotionEndsDate`. Use `AbstractShopService::unitPriceCalculation()` to normalize units to `kg` / `l` / `m` / `kos` and `getDiscount()` to compute discounts.

**Commands:**
- `AbstractCommand`: subclasses implement `executeCommand()`, not `execute()`. It adds locking through `$mode` (`MODE_SINGULAR` by default, which exits if another instance is running), timing and memory output, and logs uncaught exceptions as critical. In the `dev` environment it calls `dd()` on exceptions.
- Command names are set with `$this->setName(...)` in the constructor, before `parent::__construct()`. A few commands use `#[AsCommand(name: ...)]` instead (`protected static $defaultName` no longer works in Symfony 7).
- `AbstractSyncCommand`: sync jobs process the catalog incrementally across many cron runs. A `CommandLog` row per command stores `dailyRun` (the category/page index for the next run), `maxDailyRuns` and `completedAt`. Each run handles one category or page and increments `dailyRun`. Once all categories are done, it sets `completedAt` (so nothing else runs that day) and soft-deletes that source's products that haven't been updated for N days (`markProductsAsDeletedIfOlderThanDays`). `dailyRun` resets on the next day.
- **Scheduling:** `src/Scheduler/Schedule.php` (Symfony Scheduler) holds the production schedule as cron expressions. Each entry is a `RunScheduledCommand` message, which `RunScheduledCommandHandler` runs as a fresh `php bin/console <command>` process (never in-process: `AbstractCommand` doesn't release its lock after an exception, and the worker is long-lived). One systemd worker runs the commands one after another; `deploy.sh` restarts it with `messenger:stop-workers`. A failed or timed-out run is logged as an error.
- **Error alerts:** in prod, log records at error level and above go to Discord (`DISCORD_WEBHOOK_URL` in `.env.local`) through `filter` → `deduplication` (1 hour) → `App\Logger\DiscordWebhookHandler` in `config/packages/monolog.yaml`. The handler sends one message per request or command and skips 4xx HTTP exceptions. Deduplication compares only a message's first line, so put variable detail on later lines.
- `CheckProducts` finds the service dynamically with `$this->{$product->getSource().'Service'}`, so each service property name must match the source constant (`dmService`, `tusService`, …). Throwing `NotFoundHttpException` from `getProductData()` soft-deletes the product.

**Entities and conventions:**
- Doctrine mapping, Gedmo behaviours and routes use **PHP attributes** (`#[ORM\...]`, `#[Gedmo\...]`, `#[Route]` from `Symfony\Component\Routing\Attribute`). `doctrine/annotations` is no longer installed.
- Gedmo listeners are registered in `config/services.yaml` with one `doctrine.event_listener` tag per event (DoctrineBundle 3 dropped `doctrine.event_subscriber`).
- Shared traits are in `src/Entity/Traits` (`Id`, `Timestampable`, `GedmoSoftDeletable`). The Gedmo SoftDeleteable listener turns `remove()` into setting `deletedAt`, but no soft-delete filter is configured, so queries exclude deleted products explicitly (`p.deletedAt IS NULL`). Products are restored by setting `deletedAt = null` during sync.
- `Product` is unique on `(productId, source)`. Sources are the `Product::SOURCE_*` constants. `Product::SOURCES` maps them to display names and drives the source filter on the homepage.
- `Product.ean` holds one or more EANs as a comma-wrapped string (`,123,456,`). Use `AbstractShopService::parseEan()` to build it. Search relies on this format by matching `%,term,%`.
- Prices are `decimal(20,6)`. Compare them with `AbstractSyncCommand::normalizePrice()`.
- Products that have an `eanImage` but no EAN are added to `CommandQueueImage` for `app:queue:image`. `GeminiApi` falls back through a list of models and caches the one currently in use.

**Frontend:** there is one entry point, `assets/app.js`, which sets up Tablesort (with a Slovenian locale sort), product pinning (`/pin/{id}` toggles the `zivila-pin` cookie, and pinned products are listed first in results), Chart.js price-history modals (`/chart/{productId}` returns JSON) and the html5-qrcode barcode scanner. Tailwind only scans `templates/`, `assets/app.js` and Flowbite's JS (`@source` in `assets/styles/app.css`), which keeps the CSS build deterministic. Add an `@source` if classes come from somewhere else. Stimulus is not installed. `webpack.config.js` is an ES module (`"type": "module"`), and Encore 7 only minifies CSS because `configureCssMinimizerPlugin()` sets lightningcss explicitly. Templates are in `templates/`, and `index.html.twig` is the main page.

**Database:** the README says MySQL is used, but `compose.yaml` defines a Postgres service, which is Symfony's default scaffolding. Configure `DATABASE_URL` in `.env.local`. `GEMINI_API_KEY` is required for the image queue.
