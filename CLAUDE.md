# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Cene živil (https://www.cene-zivil.si/) compares grocery prices across Slovenian retailers (DM, Hofer, Lidl, Mercator, Spar, Tuš). Built on Symfony 5.4 and Doctrine ORM (PHP >= 7.4), with a Webpack Encore, Tailwind 4 and Flowbite frontend. User-facing text, routes and meta content are in Slovenian.

## Commands

```sh
composer install && npm install

# Frontend assets (Webpack Encore). Built output in public/build/ is committed,
# and deploy.sh does not build assets, so run `npm run build` before committing frontend changes.
npm run dev | npm run watch | npm run build

# Database
php bin/console doctrine:migrations:diff        # generate a migration after changing entity annotations
php bin/console doctrine:migrations:migrate     # deploy.sh runs `d:m:m -n`

# Local server
symfony server:start

# Data sync (run by cron in production)
php bin/console app:sync:{dm|hofer|lidl|mercator|spar|tus}
php bin/console app:check:products        # re-checks 10 stale products per run for 404s or price changes
php bin/console app:handle:promotions     # resets price to regularPrice after promotionEndsDate passes
php bin/console app:queue:image [all]     # pulls EANs out of product images with Gemini (Spar only)
php bin/console app:sitemap
php bin/console app:json-output | app:json-output-spar   # export to var/dump/gtin-dump/
```

There is no test suite (`tests/` does not exist) and no linter configured.

## Architecture

**Data flow:** a shop service (`src/Service/*Service.php`, extends `AbstractShopService`) fetches a retailer's API or HTML and normalizes each product into a plain array. A sync command (`src/Command/Sync*Command.php`, extends `AbstractSyncCommand`) passes those arrays to `updateProducts()`, which upserts `Product` rows and adds a `ProductPriceHistory` row whenever the price fields change. `IndexController` renders search results from `ProductRepository::findByTerms()`.

**Normalized item array.** Every service must return items with these keys: `source`, `productId`, `title`, `url`, `price`, `regularPrice`, `discount`, `unit`, `unitPrice`, `unitQuantity`, and optionally `ean`, `eanImage` and `promotionEndsDate`. Use `AbstractShopService::unitPriceCalculation()` to normalize units to `kg` / `l` / `m` / `kos` and `getDiscount()` to compute discounts.

**Commands:**
- `AbstractCommand`: subclasses implement `executeCommand()`, not `execute()`. It adds locking through `$mode` (`MODE_SINGULAR` by default, which exits if another instance is running), timing and memory output, and logs uncaught exceptions as critical. In the `dev` environment it calls `dd()` on exceptions.
- Command names are set with `$this->setName(...)` in the constructor, before `parent::__construct()`. A few older commands use `protected static $defaultName` instead.
- `AbstractSyncCommand`: sync jobs process the catalog incrementally across many cron runs. A `CommandLog` row per command stores `dailyRun` (the category/page index for the next run), `maxDailyRuns` and `completedAt`. Each run handles one category or page and increments `dailyRun`. Once all categories are done, it sets `completedAt` (so nothing else runs that day) and soft-deletes that source's products that haven't been updated for N days (`markProductsAsDeletedIfOlderThanDays`). `dailyRun` resets on the next day.
- `CheckProducts` finds the service dynamically with `$this->{$product->getSource().'Service'}`, so each service property name must match the source constant (`dmService`, `tusService`, …). Throwing `NotFoundHttpException` from `getProductData()` soft-deletes the product.

**Entities and conventions:**
- Doctrine mapping uses **annotations** (`@ORM\...`, `@Gedmo\...`) and routes use `@Route` annotations, not PHP attributes.
- Shared traits are in `src/Entity/Traits` (`Id`, `Timestampable`, `GedmoSoftDeletable`). Gedmo SoftDeleteable is enabled, so deleted products are hidden from queries automatically. Products are restored by setting `deletedAt = null` during sync.
- `Product` is unique on `(productId, source)`. Sources are the `Product::SOURCE_*` constants. `Product::SOURCES` maps them to display names and drives the source filter on the homepage.
- `Product.ean` holds one or more EANs as a comma-wrapped string (`,123,456,`). Use `AbstractShopService::parseEan()` to build it. Search relies on this format by matching `%,term,%`.
- Prices are `decimal(20,6)`. Compare them with `AbstractSyncCommand::normalizePrice()`.
- Products that have an `eanImage` but no EAN are added to `CommandQueueImage` for `app:queue:image`. `GeminiApi` falls back through a list of models and caches the one currently in use.

**Frontend:** there is one entry point, `assets/app.js`, which sets up Tablesort (with a Slovenian locale sort), product pinning (`/pin/{id}` toggles the `zivila-pin` cookie, and pinned products are listed first in results), Chart.js price-history modals (`/chart/{productId}` returns JSON) and the html5-qrcode barcode scanner. Stimulus is installed but not used (`import './bootstrap'` is commented out). Templates are in `templates/`, and `index.html.twig` is the main page.

**Database:** the README says MySQL is used, but `compose.yaml` defines a Postgres service, which is Symfony's default scaffolding. Configure `DATABASE_URL` in `.env.local`. `GEMINI_API_KEY` is required for the image queue.
