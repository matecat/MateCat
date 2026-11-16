#!/bin/bash
# Runs the PHPUnit suite inside the test-node container. Called by the "PHP tests" job of
# .github/workflows/_ci-cd.yml, which mounts /ci-out (reports) and /ci-cache (composer cache).
#
# Example: docker compose -f docker/docker-compose-ci.yml run --rm test-node \
#          /var/www/matecat/.github/ci/php-tests.sh
set -euo pipefail
cd /var/www/matecat

until mysql -h mysql -u root -e "status" > /dev/null 2>&1; do
    echo "=> Waiting for MySQL"
    sleep 2
done

mysql -u root -h mysql -e "SET @@GLOBAL.sql_mode='NO_ENGINE_SUBSTITUTION';"
mysql -u root -h mysql -e "SET @@GLOBAL.explicit_defaults_for_timestamp='ON';"
mysql -u root -h mysql -e "SET @@GLOBAL.character_set_server='utf8';"

php -r "readfile('https://getcomposer.org/installer');" | php
php composer.phar -n install
rm composer.phar

./vendor/bin/phpunit --configuration ./phpunit.xml --coverage-clover=/ci-out/php-coverage.xml
