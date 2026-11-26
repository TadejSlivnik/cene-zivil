set -e

# Assets are built on the server (public/build is not committed). Fail before touching anything
# if Node is missing or too old for Encore 7.
node -e 'const [a, b] = process.versions.node.split(".").map(Number); process.exit(a > 22 || (a === 22 && b >= 18) ? 0 : 1)' \
    || { echo "deploy.sh: Node.js 22.18+ is required to build the assets" >&2; exit 1; }

# The first pull after public/build stopped being committed deletes it; keep serving a copy until the new build is in place.
mkdir -p var
[ -d public/build ] && rm -rf var/build-before-pull && cp -a public/build var/build-before-pull
git pull
[ -d public/build ] || { [ -d var/build-before-pull ] && mv var/build-before-pull public/build; }
rm -rf var/build-before-pull

# Install first: migrations and cache:clear (a composer post-install script) need the new vendor code.
# --no-dev skips the test and analysis tools.
composer install --no-dev --no-interaction

# Build into a staging directory and swap it in, so pages never see a half-built public/build.
npm ci --no-audit --no-fund
rm -rf public/build-next public/build-prev
ENCORE_OUTPUT_PATH=public/build-next npm run build
[ -d public/build ] && mv public/build public/build-prev
mv public/build-next public/build
rm -rf public/build-prev

php bin/console d:m:m -n

# The scheduler worker (deploy/cene-zivil-scheduler.service) keeps the old code loaded: ask it to exit after the
# command it is running, and systemd starts it again on the new code.
php bin/console messenger:stop-workers
