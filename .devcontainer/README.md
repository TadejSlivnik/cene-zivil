# VSCode Dev Container Setup

1. Open the project in VSCode.
2. When prompted, reopen in container (or use Command Palette: "Dev Containers: Reopen in Container").
3. The container will build using the provided Dockerfile.
4. Composer and npm dependencies will be installed automatically.
5. Access Symfony via `localhost:8000` (or configured port).

## Requirements

- Docker Desktop
- VSCode with Dev Containers extension

## Customization

- Edit `.devcontainer/Dockerfile` for PHP/Node versions or extra tools.
- Update `.devcontainer/devcontainer.json` for extensions or settings.
