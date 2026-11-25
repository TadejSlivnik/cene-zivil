set -e

git pull
# Install first: migrations and cache:clear (a composer post-install script) need the new vendor code.
# --no-dev skips the test and analysis tools.
composer install --no-dev --no-interaction
php bin/console d:m:m -n
