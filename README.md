# bitrix-orm-migrations
Автоматическое создание и применение файлов миграций БД для ORM 1С-Битрикс. 

## Установка

`composer require p0n0marev/bitrix-orm-migrations`

### Как использовать из консоли на примере symfony/console.

Устанавливаем компонент
`composer require symfony/console`

создаем файл с примерно таким содержимым
```
#!/usr/bin/env php
<?php
define("NOT_CHECK_PERMISSIONS", true);
$_SERVER["DOCUMENT_ROOT"] = __DIR__ . DIRECTORY_SEPARATOR . '../www';
require $_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php";

use \Symfony\Component\Console\Application;

require __DIR__ . '/../vendor/autoload.php';

$application = new Application();
$application->add(new \P0n0marev\BitrixMigrations\Command());
$application->add(new \P0n0marev\BitrixMigrations\Command());
$application->add(new \P0n0marev\BitrixMigrations\Command());
$application->add(new \P0n0marev\BitrixMigrations\Command());
$application->run();
```

Запускаем `php app/console.php migrations:status`

Доступные команды

`migrations:status` количество доступных  
`migrations:diff` cоздать файл миграции  
`migrations:migrate` запустить необработаные 
`migrations:rollback` откатить последнюю  

Подробнее о symfony/console http://symfony.com/doc/current/console.html

## Как использовать в скрипте

```
<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

/** @var string каталог в котором будут хранится файлы миграций */
$dir = $_SERVER['DOCUMENT_ROOT'] . '/local/migrations';

/** @var array список сущностей для которых будут создаваться миграции */
$entity = [
	\P0n0marev\Module\TestTable::class,
];

$migrate = new P0n0marev\BitrixMigrations\Migrate($dir);
$migrate->setManagers($entity);

// Создать файл миграции
$migrate->diff();
// запустить необработаные
$migrate->migrate();
// количество доступных
$migrate->status();
// откатить последнюю
$migrate->rollback();
```

История выполненных миграций будет хранится в таблице `migration_versions`. Таблица будет создана автоматически при необходимости.
