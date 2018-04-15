# bitrix-orm-migrations
Автоматическое создание и применение файлов миграций БД для ORM 1С-Битрикс. 

## Установка

`composer require p0n0marev/bitrix-orm-migrations`

## Как использовать

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
```

История выполненных миграций будет хранится в таблице `migration_versions`. Таблица будет создана автоматически при необходимости.
