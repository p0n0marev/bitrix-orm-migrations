<?php
namespace P0n0marev\Bitrix\Migrations\Lib;

abstract class AbstractMigration
{
	abstract function up($connection);

	abstract function down($connection);
}