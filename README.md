# Cene živil

Website: https://www.cene-zivil.si/

A simple open-source web application for comparing grocery prices in Slovenia. Built with Symfony, Doctrine, Webpack Encore, and modern JavaScript (ES6+), it aggregates and displays product prices from major Slovenian retailers.

## Features

- Search and compare grocery prices from multiple stores
- Historical price tracking
- Responsive, mobile-friendly UI (Tailwind CSS)
- Scheduled data synchronization via Symfony console commands
- Interactive charts and sortable tables

## Tech Stack

- **Backend:** PHP, Symfony 7.4 LTS, Doctrine ORM 3
- **Frontend:** JavaScript (ES6), Chart.js, Flowbite, Tailwind CSS
- **Build Tools:** Webpack Encore, PostCSS
- **Database:** MySQL (configurable)

## Getting Started

### Prerequisites

- PHP 8.4+
- Composer
- Node.js 22.18+ & npm (only needed to rebuild assets)
- MariaDB / MySQL (production and tests use MariaDB 11.3)

### Installation

1. **Clone the repository:**
   ```sh
   git clone https://github.com/TadejSlivnik/cene-zivil.git
   cd cene-zivil
   ```
2. **Install PHP dependencies:**
   ```sh
   composer install
   ```
3. **Install JS dependencies:**
   ```sh
   npm install
   ```
4. **Configure environment:**
   - Copy `.env` to `.env.local` and set your database credentials.
5. **Run database migrations:**
   ```sh
   php bin/console doctrine:migrations:migrate
   ```
6. **Start the Symfony server:**
   ```sh
   symfony server:start
   # (or use your preferred local web server)
   ```

## Development

- **Frontend:** Edit files in `assets/` (JS, CSS) and run `npm run build` (or `npm run watch`). `public/build/` is not committed; `deploy.sh` builds it on the server, so the server needs Node.js 22.18+.
- **Backend:** Symfony code in `src/`
- **Templates:** Twig files in `templates/`

### Tests and checks

The tests need a MariaDB server (the production engine). One way to get one:

```sh
docker run -d --name cene-zivil-test-db -e MARIADB_ROOT_PASSWORD=root -p 127.0.0.1:3306:3306 mariadb:11.3
```

`.env.test` expects `root:root@127.0.0.1:3306`. If your server differs, put your own `DATABASE_URL` in `.env.test.local` (not committed). Then build the assets (the page tests render templates that read `public/build/entrypoints.json`) and create the test database once (`_test` is appended to the name):

```sh
npm run build
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test -n
```

| Command | What it does |
|---|---|
| `composer test` | PHPUnit. Each test runs in a transaction that is rolled back, so the database stays empty |
| `composer phpstan` | PHPStan level 5. Existing issues are listed in `phpstan-baseline.neon`; new code must be clean |
| `composer cs` / `composer cs-fix` | Check / fix code style (PHP-CS-Fixer, `@Symfony` rules) |
| `composer lint` | Lint the service container, YAML config and Twig templates |
| `composer ci` | Everything above, as CI runs it |

Shop services are tested against small fixtures in `tests/Fixtures/shops/` that copy the real API response structure; the tests never call the shops.

### CI

GitHub Actions (`.github/workflows/ci.yml`) runs on every pull request and on pushes to `master`:

- **PHP** (PHP 8.5, MariaDB 11.3): `composer validate`, `composer audit`, lint, code style, PHPStan, migrations from an empty database plus `doctrine:schema:validate`, PHPUnit
- **Frontend** (Node from `.nvmrc`): `npm ci`, `npm audit`, `npm run build`. The PHP job runs after it and uses that build for the page tests

Dependabot (`.github/dependabot.yml`) opens weekly grouped update PRs for Composer, npm and GitHub Actions. Symfony major versions are ignored (the app stays on the 7.4 LTS line).

## License

MIT. See [LICENSE](LICENSE) for details.

## Contributing

Pull requests and issues are welcome! For major changes, please open an issue first to discuss what you would like to change.

---

**Cene živil** is not affiliated with any retailer. Data is for informational purposes only.
