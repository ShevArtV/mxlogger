# mxLogger

Компонент логирования процессов для **MODX Revolution 2** (PHP 7.4+).

Расставьте вызовы логгера с общими тэгами (например `purchase`, `cart`) — и найдите
все записи по тэгу в менеджере. У записи может быть несколько тэгов. Записи идут по
порядку (автоинкремент `id` + `createdon`).

Тэги: lowercase, только латиница и цифры; хранятся в CSV-колонке `tags` с FULLTEXT-индексом.

## Структура

```
core/components/mxlogger/
  model/mxlogger/          сервис-классы и xPDO-модель
    mxlogger.class.php       движок (log, фильтры, захват backtrace)
    mxloggerprocess.class.php скоуп одного процесса (tag + process_uid)
    mxloggerlog.class.php    xPDO-объект записи
  model/schema/            xml-схема
  processors/mgr/log/      getlist, get, remove, clear, gettags
  controllers/             CMP-контроллер
  templates/               tpl страницы
  lexicon/{en,ru}/         переводы
  elements/snippets/       сниппет mxLogger (для чанков/Fenom)
  docs/                    readme/changelog/license
assets/components/mxlogger/
  connector.php            менеджерный коннектор
  js/mgr/                  ExtJS: грид, окно детали, панель
  css/mgr/main.css
modxbuilder/mxlogger/      сборка пакета (см. modxbuilder/README.md)
```

## Использование

Сервис автозагружается (extension_packages) и доступен как `$modx->mxlogger` — `getService()` не нужен.

```php
$mxl = $modx->mxlogger;

$mxl->info('purchase', 'Корзина создана', ['cart_id' => $id]);
$mxl->info(['cart', 'purchase'], 'Товар добавлен', ['product' => $pid]);

$p = $mxl->process(['cart', 'purchase']); // авто process_uid
$p->info('Старт оплаты', ['order' => 42]);
$p->error('Платёж отклонён', ['code' => 'declined']);
```

Подробности API, режимы захвата трассировки и фильтры записи — в
[`core/components/mxlogger/docs/readme.txt`](core/components/mxlogger/docs/readme.txt).

## Сборка пакета

Источник кода — `core/components/mxlogger/` и `assets/components/mxlogger/`.
Схема/модель и упаковка `.transport.zip` — через `modxbuilder/mxlogger/build/*`
(запускать на живом MODX). Таблица создаётся PHP-резолвером при install/upgrade;
при uninstall таблица **не** удаляется (чтобы не потерять логи).

Версия: 1.0.0-rc2.
