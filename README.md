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

Самый короткий способ — фасад **`$modx->mxl`** (с версии 1.2.0). Доступен сразу
из любого сниппета/плагина/чанка, `getService()` не нужен. Тот же `$modx->mxl`
работает и в [версии под MODX 3](https://github.com/ShevArtV/mxlogger3) — **API
вызовов одинаков в обеих версиях**, отличается лишь способ получить сервис, если
фасад недоступен (см. ниже).

```php
$modx->mxl->debug('purchase', 'Открыта корзина');
$modx->mxl->info('purchase', 'Корзина создана', ['cart_id' => $id]);
$modx->mxl->warning('purchase', 'Низкий остаток', ['left' => 2]);
$modx->mxl->error('purchase', 'Платёж отклонён', ['code' => 'declined']);

// Несколько тэгов на одну запись:
$modx->mxl->info(['cart', 'purchase'], 'Товар добавлен', ['product' => $pid]);

// Процесс — одна воронка с общим process_uid:
$p = $modx->mxl->process(['cart', 'purchase']); // авто process_uid
$p->info('Старт оплаты', ['order' => 42]);
$p->error('Платёж отклонён', ['code' => 'declined']);
$uid = $p->getUid();                            // можно сохранить и продолжить позже
```

Сигнатура: `log($tags, $level, $message, array $context = [], array $options = [])`
+ шорткаты `debug/info/warning/error`. Уровни: `debug` / `info` / `warning` / `error`.

Из чанка или Fenom:

```
[[!mxLogger? &tags=`cart,purchase` &level=`info` &message=`Товар добавлен`]]
```

**Если фасад недоступен** (версия ниже 1.2.0 или очень ранний этап загрузки) —
сервис также доступен как `$modx->mxlogger` (extension_packages) или через
`$modx->getService('mxlogger', 'mxLogger', MODX_CORE_PATH . 'components/mxlogger/model/mxlogger/')`.

Подробности API, режимы захвата трассировки и фильтры записи — в
[`core/components/mxlogger/docs/readme.txt`](core/components/mxlogger/docs/readme.txt).

## Менеджер

Логи — в «Компоненты → mxLogger»: грид с фильтрами (тэги, уровень, процесс,
пользователь/сессия/IP, период, поиск по тексту), окно детали и очистка. Кнопка
**Экспорт** в тулбаре выгружает записи с учётом текущих фильтров в Markdown
(`.md`) или текст (`.txt`).

## Сборка пакета

Источник кода — `core/components/mxlogger/` и `assets/components/mxlogger/`.
Схема/модель и упаковка `.transport.zip` — через `modxbuilder/mxlogger/build/*`
(запускать на живом MODX). Таблица создаётся PHP-резолвером при install/upgrade;
при uninstall таблица **не** удаляется (чтобы не потерять логи).

Актуальная версия и история изменений — в
[`core/components/mxlogger/docs/changelog.txt`](core/components/mxlogger/docs/changelog.txt).
