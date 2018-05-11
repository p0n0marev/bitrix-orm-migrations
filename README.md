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
$application->add(new \P0n0marev\BitrixMigrations\Command\DiffCommand());
$application->add(new \P0n0marev\BitrixMigrations\Command\MigrateCommand());
$application->add(new \P0n0marev\BitrixMigrations\Command\RollbackCommand());
$application->add(new \P0n0marev\BitrixMigrations\Command\StatusCommand());
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

$migrate = new P0n0marev\BitrixMigrations\Migrate();

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
