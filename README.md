# Cene živil

A simple open-source web application for comparing grocery prices in Slovenia. Built with Symfony, Doctrine, Webpack Encore, and modern JavaScript (ES6+), it aggregates and displays product prices from major Slovenian retailers.

## Features

- Search and compare grocery prices from multiple stores
- Historical price tracking
- Responsive, mobile-friendly UI (Tailwind CSS)
- Scheduled data synchronization via Symfony console commands
- Interactive charts and sortable tables

## Tech Stack

- **Backend:** PHP, Symfony Framework, Doctrine ORM
- **Frontend:** JavaScript (ES6), Stimulus, Chart.js, Flowbite, Tailwind CSS
- **Build Tools:** Webpack Encore, PostCSS
- **Database:** MySQL (configurable)

## Getting Started

### Prerequisites

- PHP 8.1+
- Composer
- Node.js & npm
- MySQL (or compatible database)

### Installation

#### Option 1: Dev Container (Recommended)

1. Open the project in VS Code.
2. Reopen in container when prompted, or use Command Palette: "Dev Containers: Reopen in Container".
3. The container will build using `.devcontainer/Dockerfile` and install all dependencies automatically.
4. Access Symfony via `localhost:8000` (or configured port).

#### Option 2: Manual Setup

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

- **Frontend:** Edit files in `assets/` (JS, CSS)
- **Backend:** Symfony code in `src/`
- **Templates:** Twig files in `templates/`

## License

MIT. See [LICENSE](LICENSE) for details.

## Contributing

Pull requests and issues are welcome! For major changes, please open an issue first to discuss what you would like to change.

---

**Cene živil** is not affiliated with any retailer. Data is for informational purposes only.
