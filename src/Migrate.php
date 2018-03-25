<?php

namespace P0n0marev\Bitrix\Migrations;


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

    /**
     * Инициализация. Будет создан каталог, таблица и базовый класс миграции
     */
    public function init()
    {
        $this->getDirectory();

        $connection = $this->getConnection();
        if (!$connection->isTableExists(MigrationVersionsTable::getEntity()->getDBTableName())) {
            MigrationVersionsTable::getEntity()->createDbTable();
        }

        $managers = $this->getManagers();

        $up = [];
        foreach ($managers as $manager) {

            /** $entity таблица */
            $entity = \Fleetcare\Portal\TestTable::getEntity();
            /** $tableName ее имя */

            $structureDump = $entity->compileDbTableStructureDump();

            foreach ($structureDump as $query) {
                $up[] = $query;
            }

        }

        $version = time();
        $path = $this->generateMigration($version, $up);
        $this->saveVersion($version);

        print(sprintf('Generated new migration class to "<info>%s</info>" from schema differences.', $path));
        print(file_get_contents($path));
    }

    /**
     * Создание миграции
     */
    public function diff()
    {
        $connection = $this->getConnection();

        /** $entity таблица */
        $entity = \Fleetcare\Portal\TestTable::getEntity();
        /** $tableName ее имя */
        $tableName = $entity->getDBTableName();

        $up = [];
        $down = [];

        if (!$connection->isTableExists($tableName)) { // такой таблицы еще нет в базе создаем ее

            $structureDump = $entity->compileDbTableStructureDump();

            foreach ($structureDump as $query) {
                $up[] = $query;
            }
        } else { // обновление

            $fromFields = $connection->getTableFields($tableName);
            $toFields = \Fleetcare\Portal\TestTable::getEntity()->getFields();

            do {

                $from = array_shift($fromFields);

                if (isset($toFields[$from->getName()])) {
                    $to = $toFields[$from->getName()];
                    unset($toFields[$from->getName()]);
                } else {
                    // не найдено такого поля в новой конфигурации - удаление
                    $up[] = $this->getDropQuery($tableName, $from);
                    $down[] = $this->getAddQuery($tableName, $from);
                    continue;
                }

                // если изменилось имя
                if ($from->getName() != $to->getName()) {
                    $up[] = $this->getChangeQuery($tableName, $from, $to);
                    $down[] = $this->getChangeQuery($tableName, $to, $from);
                }


                $fromSql = $this->getFieldSql($from);
                $toSql = $this->getFieldSql($to);

                if ($fromSql === $toSql) continue;

                // если изменился тип или параметры
                $up[] = $this->getModifyQuery($tableName, $to);
                $down[] = $this->getModifyQuery($tableName, $from);

            } while (!empty($fromFields));


            // если есть новые поля
            if (count($toFields)) {
                foreach ($toFields as $to) {
                    $up[] = $this->getAddQuery($tableName, $to);
                    $down[] = $this->getDropQuery($tableName, $to);
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
    }

    /**
     *
     */
    public function status()
    {
        $new = $this->getNewMigrations();

        print sprintf('Новый миграций: %d', count($new));
    }


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
        $new = $this->getNewMigrations();
        foreach ($new as $version) {

            require $this->getDirectory() . DIRECTORY_SEPARATOR . 'Version' . $version . '.php';

            $class = 'p0n0marev\Bitrix\Migrations\Version' . $version;
            $migrate = new $class();

            $migrate->up();

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
        $this->managers = $managers;
    }


    /**
     * тип и параметры поля
     * @param \Bitrix\Main\Entity\ScalarField $field
     * @return array
     */
    private function getFieldSql(\Bitrix\Main\Entity\ScalarField $field)
    {
        $realColumnName = $field->getColumnName();

        /** @var string $defaultValue значение по умолчанию */
        $defaultValue = '';
        if ($field->getDefaultValue()) {
            if ($field->getDefaultValue() instanceof \Bitrix\Main\Type\Date
                || $field->getDefaultValue() instanceof \Bitrix\Main\Type\DateTime
            ) {
                $defaultValue = ' DEFAULT 0';
            } else {
                $defaultValue = ' DEFAULT ' . $field->getDefaultValue();
            }
        }

        return $this->getSqlHelper()->getColumnTypeByField($field)
        . $defaultValue
        . ' NOT NULL' // null for oracle if is not primary
            ;
    }

    /**
     * Запрос на удаление поля
     * @param $tableName
     * @param $field
     * @return string
     */
    private function getDropQuery($tableName, $field)
    {
        $query = 'ALTER TABLE ' . $this->getSqlHelper()->quote($tableName) . ' DROP COLUMN ';
        $query .= $this->getSqlHelper()->quote($field->getName()) . ';';

        return $query;
    }

    /**
     * Запрос на переименование поля
     * @param $tableName
     * @param $from
     * @param $to
     * @return string
     */
    private function getChangeQuery($tableName, $from, $to)
    {
        $query = 'ALTER TABLE ' . $this->getSqlHelper()->quote($tableName) . ' CHANGE ';
        $query .= $this->getSqlHelper()->quote($from->getName()) . ' ';
        $query .= $this->getSqlHelper()->quote($to->getName()) . ' ';
        $query .= $this->getFieldSql($to) . ';';

        return $query;
    }

    /**
     * вернет команду для изменения поля
     * @param $tableName
     * @param $field
     * @return string
     */
    private function getModifyQuery($tableName, $field)
    {
        $query = 'ALTER TABLE ' . $this->getSqlHelper()->quote($tableName) . ' MODIFY ';
        $query .= $this->getSqlHelper()->quote($field->getName()) . ' ';
        $query .= $this->getFieldSql($field) . ';';

        return $query;
    }

    /**
     * вернет команду для добавления поля
     * @param $tableName
     * @param $field
     * @return string
     */
    private function getAddQuery($tableName, $field)
    {
        $query = 'ALTER TABLE ' . $this->getSqlHelper()->quote($tableName) . ' ADD ';
        $query .= $this->getSqlHelper()->quote($field->getName()) . ' ';
        $query .= $this->getFieldSql($field) . ';';

        return $query;
    }


    /**
     * @return null
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
    public function up()
    {
        // this up() migration is auto-generated, please modify it to your needs
        $connection = \Bitrix\Main\Application::getConnection();
		<up>
    }

    /**
     */
    public function down()
    {
        // this down() migration is auto-generated, please modify it to your needs
		$connection = \Bitrix\Main\Application::getConnection();
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