<?php
namespace P0n0marev\Bitrix\Migrations\Entity;

use \Bitrix\Main\Entity;

class MigrationVersionsTable extends Entity\DataManager
{

	public static function getTableName()
	{
		return 'migration_versions';
	}

	public static function getUfId()
	{
		return 'MIGRATION_VERSIONS';
	}

	public static function getMap()
	{
		return array(
			new Entity\IntegerField('VERSION', array(
				'primary' => true,
				'required' => true,
			)),
		);
	}

}