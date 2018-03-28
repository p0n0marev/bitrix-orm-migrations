<?php
namespace P0n0marev\BitrixMigrations\Lib;

abstract class AbstractMigration
{
	abstract function up($connection);

	abstract function down($connection);
}