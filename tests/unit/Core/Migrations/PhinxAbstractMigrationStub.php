<?php

namespace Phinx\Migration;

/**
 * Phinx is not a dependency of the application, so the migrations cannot be loaded in a test
 * without a parent class. This empty stand-in lets a test read a migration's `sql_up` and run it
 * on the test connection itself.
 */
abstract class AbstractMigration
{
}
