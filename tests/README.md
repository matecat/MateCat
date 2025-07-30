# Matecat Test Suite

## Database Schema and Data Seed

The `.travis.yml` file loads the database schema into the MySQL test database on Travis CI. To run tests locally, you must load the schema yourself, but only once.

Seed data is also inserted into your test database during this process.

## About Tests Organization

Test suites are defined in the `phpunit.xml` file.

## How to Configure the Test Environment

There are two configuration files inside the `tests/inc` directory:

- `config.local.ini`
- `config.travis.ini`

The `test_helper.php` script loads the correct configuration file based on an environment variable.
It is launched by PHPUnit as configured in the `phpunit.xml` file.

## How to Run Tests

Tests can be run with the following command:

```
vendor/bin/phpunit
```

Specific tests can be run with:

```
vendor/bin/phpunit --filter=[test_name_here]
```

which is useful during development.
