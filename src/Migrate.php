<?php

namespace P0n0marev\Bitrix\Migrations;
use P0n0marev\Bitrix\Migrations\Entity\MigrationVersionsTable;
use P0n0marev\Bitrix\Migrations\Lib\Field;


/**
 * Миграции структуры БД на основе ORM Битрикс
 *
 * Class bitrixORMMigrations
 */
class Migrate
{
	private $connection = null;
	private $sqlHelper = null;
	private $directory = null;
	/** @var array список классов сущностей */
	private $managers = [];

    public function __construct($directory)
	{
		$this->setDirectory($directory);

		$connection = $this->getConnection();
		if (!$connection->isTableExists(MigrationVersionsTable::getEntity()->getDBTableName())) {
			MigrationVersionsTable::getEntity()->createDbTable();
		}
	}

	/**
	 * Создание миграции
	 */
	public function diff()
	{
		$connection = $this->getConnection();

		$up = [];
		$down = [];

		$managers = $this->getManagers();
		foreach ($managers as $manager) {

			/** $entity таблица */
			$entity = $manager->getEntity();
			/** $tableName ее имя */
			$tableName = $entity->getDBTableName();

			if (!$connection->isTableExists($tableName)) { // такой таблицы еще нет в базе создаем ее

				$structureDump = $entity->compileDbTableStructureDump();

				foreach ($structureDump as $query) {
					$up[] = $query;
				}
			} else { // обновление

				$fromFields = $this->getFieldsFromSchema($tableName);
				$toFields = $this->getFieldsFromOrm($entity);

				do {

					$from = array_shift($fromFields);

					if (isset($toFields[$from->getName()])) {
						$to = $toFields[$from->getName()];
						unset($toFields[$from->getName()]);
					} else {
						// не найдено такого поля в новой конфигурации - удаление
						$up[] = $from->getDropQuery();
						$down[] = $from->getAddQuery();
						continue;
					}

					if ($from->equals($to)) continue;

					// если изменилось имя
					if ($from->getName() != $to->getName()) {
						$up[] = $from->getChangeQuery($to);
						$down[] = $to->getChangeQuery($from);
					}


					$up[] = $from->getModifyQuery($to);
					$down[] = $to->getModifyQuery($from);

				} while (!empty($fromFields));


				// если есть новые поля
				if (count($toFields)) {
					foreach ($toFields as $to) {

						$up[] = $to->getAddQuery();
						$down[] = $to->getDropQuery();
					}
				}
			}

		}

		if (!$up && !$down) {
			print ('No changes detected in your mapping information.');
			return;
		}


		$version = time();
		$path = $this->generateMigration($version, $up, $down);

		print(sprintf('Generated new migration class to "<info>%s</info>" from schema differences.', $path));
		print(file_get_contents($path));
	}

	/**
	 * @return string
	 */
	public function status()
	{
		$new = $this->getNewMigrations();

		print sprintf('Новых миграций: %d', count($new));
	}


    /**
     * @return array
     */
	public function getNewMigrations()
	{
		$installed = [];
		$rs = MigrationVersionsTable::getList([
			'select' => ['VERSION'],
			'order' => ['VERSION' => 'ASC'],
		]);
		while ($m = $rs->fetch()) {
			$installed[] = $m['VERSION'];
		}

		$all = [];
		$files = glob($this->getDirectory() . '/Version*.php');
		foreach ($files as $file) {
			$all[] = substr(current(explode('.', basename($file))), 7);
		}

		return array_diff($all, $installed);
	}

	/**
	 *
	 */
	public function migrate()
	{
		$connection = $this->getConnection();

		$new = $this->getNewMigrations();
		foreach ($new as $version) {

			$namespace = '\\';

			$filePath = $this->getDirectory() . DIRECTORY_SEPARATOR . 'Version' . $version . '.php';
			$content = file_get_contents($filePath);

			foreach (explode("\n", $content) as $l) {
				if (strpos($l, 'namespace') !== false) {

					$l = str_replace(';', '', $l);
					$l = str_replace('namespace', '', $l);
					$l = trim($l);
					$namespace = '\\' . $l;
					break;
				}
			}

			require $this->getDirectory() . DIRECTORY_SEPARATOR . 'Version' . $version . '.php';

			$class = $namespace . '\Version' . $version;
			$migrate = new $class();

			$migrate->up($connection);

			$this->saveVersion($version);
		}

	}

	/**
	 * @return mixed
	 */
	public function getConnection()
	{
		if ($this->connection === null)
			$this->connection = \Bitrix\Main\Application::getConnection();

		return $this->connection;

	}

	/**
	 * @return mixed
	 */
	public function getSqlHelper()
	{
		if ($this->sqlHelper == null)
			$this->sqlHelper = new \Bitrix\Main\DB\MysqliSqlHelper($this->getConnection());

		return $this->sqlHelper;
	}

	/**
	 * @return array
	 */
	public function getManagers()
	{
		return $this->managers;
	}

	/**
	 * @param array $managers
	 */
	public function setManagers($managers)
	{
		foreach ($managers as &$manager) {
			if (!is_object($manager))
				$manager = new $manager;
		}
		$this->managers = $managers;
	}

	/**
	 * тип и параметры поля из базы
	 * @param string $tableName
	 * @return array
	 */
	private function getFieldsFromSchema($tableName)
	{

		$connection = $this->getConnection();
		$sqlHelper = $this->getSqlHelper();

		$result = [];

		$sql = "
			SELECT
			  COLUMN_NAME,
			  COLUMN_COMMENT,
			  DATA_TYPE,
			  COLUMN_TYPE
			FROM information_schema.COLUMNS
			WHERE TABLE_NAME = '" . $sqlHelper->forSql($tableName, 200) . "'
		";

		$rs = $connection->query($sql);
		while ($record = $rs->fetch()) {

			$field = new Field();
			$field->setTableName($tableName);
			$field->setName($record['COLUMN_NAME']);
			$field->setTitle($record['COLUMN_COMMENT']);

			// как в Bitrix\Main\DB\MysqlCommonSqlHelper::getColumnTypeByField(Entity\ScalarField $field)
			if ($record['DATA_TYPE'] == 'int') {
				$field->setType('int');
			} else {
				$field->setType($record['COLUMN_TYPE']);
			}

			$result[$record['COLUMN_NAME']] = $field;
		}

		return $result;
	}

	/**
	 * тип и параметры поля из ORM
	 * @param Entity $entity
	 * @return array
	 */
	private function getFieldsFromOrm($entity)
	{
		$sqlHelper = $this->getSqlHelper();

		$result = [];
		$arFields = $entity->getFields();
		foreach ($arFields as $fieldOrm) {

			if ($fieldOrm instanceof Entity\ReferenceField) continue;
			if ($fieldOrm instanceof Entity\ExpressionField) continue;

			$field = new Field();
			$field->setTableName($entity->getDBTableName());
			$field->setName($fieldOrm->getName());
			$field->setTitle($fieldOrm->getTitle());
			$field->setType($sqlHelper->getColumnTypeByField($fieldOrm));

			$result[$fieldOrm->getName()] = $field;

		}

		return $result;
	}

	/**
     * Вернет каталог для хранения миграций
     * Каталог будет создан если не существует
	 * @return string
	 */
	public function getDirectory()
	{
		if (!file_exists($this->directory)) {
			if (!mkdir($this->directory, 0777, true)) {
				die('Не удалось создать директории...');
			}
		}

		return $this->directory;
	}

	/**
	 * @param null $directory
	 */
	public function setDirectory($directory)
	{
		$this->directory = $directory;
	}

	private function generateMigration($version, $up = [], $down = [])
	{

		$_template =
			'<?php
namespace p0n0marev\Bitrix\Migrations;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version<version> extends AbstractMigration
{
    /**
     */
    public function up($connection)
    {
        // this up() migration is auto-generated, please modify it to your needs
		<up>
    }

    /**
     */
    public function down($connection)
    {
        // this down() migration is auto-generated, please modify it to your needs
		<down>
    }
}
';

		$up = array_map(function ($query) {
			return '$connection->query("' . $query . '");';
		}, $up);
		$down = array_map(function ($query) {
			return '$connection->query("' . $query . '");';
		}, $down);


		$placeHolders = [
			'<version>',
			'<up>',
			'<down>',
		];
		$replacements = [
			$version,
			$up ? implode("\n        ", $up) : null,
			$down ? implode("\n        ", $down) : null
		];
		$code = str_replace($placeHolders, $replacements, $_template);

		$dir = $this->getDirectory();
		$path = $dir . '/Version' . $version . '.php';

		file_put_contents($path, $code);

		return $path;
	}

	private function saveVersion($version)
	{
		MigrationVersionsTable::add(['VERSION' => $version]);
	}

}
