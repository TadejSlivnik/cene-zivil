set -e

git pull
# Install first: migrations and cache:clear (a composer post-install script) need the new vendor code.
composer install --no-interaction
php bin/console d:m:m -n
