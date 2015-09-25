# How to configure test environment

In your `config.ini` file you need to include a `test` section where to put
configuration for the test database file. It is also advised to use a separate
`STORAGE_DIR`.

The bootstrap.php file copies the schema form your development database
to the test one.  Seed data is also inserted in your test database in this process.

** WARNING **

Runnig tests drops and restores the test database. Also there's currently no
namespace for log files, so it is advised to use a separate STORAGE_DIR.

# How to run tests

Tests can be run with the following command:

`vendor/bin/phpunit`

Specific tests can be run with:

`vendor/bin/phpunit --filter=[test_name_here]`

which is useful during development.

# About tests organization

Test suites are defined in `phpunit.xml` file.

# About legacy tests

Test directory currently includes a `Tests` folder where legacy tests reside.
This folder is not executed since recent changes made the suite outdated.
