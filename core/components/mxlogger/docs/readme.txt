<strong>mxLogger</strong> — удобное логирование процессов в MODX Revolution 2.

Расставьте вызовы логгера с общими тэгами (например «purchase» и «cart») — и в менеджере найдёте все записи процесса по тэгу. В комплекте — готовый плагин, логирующий корзину и оформление заказа miniShop2, и автономный просмотрщик логов в обход MODX.

<strong>Возможности</strong>
<ul>
<li>Логирование с тэгами; у одной записи может быть <em>несколько</em> тэгов.</li>
<li>Группировка одной воронки через идентификатор процесса.</li>
<li>Автозахват источника вызова: класс, метод, файл, строка.</li>
<li>Для уровней «warning» и «error» — стэк вызовов и параметры (объекты сворачиваются в имя класса).</li>
<li>Автоматически пишутся пользователь, сессия, ip, время.</li>
<li>Фильтры записи: по пользователю, группе, сессии, куке.</li>
<li>Менеджерный грид с фильтрами, окном детали и кликом по значению для фильтрации.</li>
<li>Просмотр логов в обход MODX — CLI и WEB.</li>
</ul>

<strong>Требования к окружению</strong>
<ul>
<li>MODX Revolution 2.x (проверено на 2.8.5).</li>
<li>PHP 7.4 или новее.</li>
<li>MySQL 5.6+ / MariaDB на InnoDB — нужен FULLTEXT-индекс по тэгам.</li>
<li>Расширения PHP: PDO, mbstring, json.</li>
<li>Плагин логирования корзины/заказа работает только при установленном <a href="https://modstore.pro/packages/ecommerce/minishop2">miniShop2</a>.</li>
</ul>

<strong>Установка</strong>
<ul>
<li>Установите пакет через «Управление пакетами».</li>
<li>Создаются автоматически: таблица логов, namespace, меню «Компоненты → mxLogger», системные настройки, сниппет mxLogger и плагин mxLoggerMiniShop2.</li>
</ul>

<strong>Как добавлять логирование в свой код</strong>
После установки сервис зарегистрирован в extension_packages и доступен сразу как $modx->mxlogger — getService вызывать не нужно. Для краткости в примерах используем $mxl:
<code>$mxl = $modx->mxlogger;

$mxl->debug('purchase', 'Открыта корзина');
$mxl->info('purchase', 'Корзина создана', ['cart_id' => $id]);
$mxl->warning('purchase', 'Низкий остаток', ['left' => 2]);
$mxl->error('purchase', 'Платёж отклонён', ['code' => 'declined']);</code>

Несколько тэгов на одну запись:
<code>$mxl->info(['cart', 'purchase'], 'Товар добавлен', ['product' => $pid]);</code>

Процесс — один экземпляр воронки = один общий идентификатор:
<code>$p = $mxl->process(['cart', 'purchase']); // идентификатор сгенерируется автоматически
$p->info('Старт оплаты', ['order' => 42]);
$p->error('Платёж отклонён', ['code' => 'declined']);
$uid = $p->getUid();                       // можно сохранить и продолжить позже</code>

Принудительно снять полный стэк и параметры для конкретной записи:
<code>$mxl->info('purchase', 'Создан заказ', $ctx, ['trace' => true]);</code>

Если логируете через свою обёртку/фасад над сервисом — укажите её класс в опции skip_classes, чтобы «Источник» указывал на реальный вызывающий код, а не на обёртку (надёжнее жёсткого skip=N). Можно точное имя класса или префикс пространства имён со «\» на конце:
<code>$mxl->info('payment', 'Платёж создан', $ctx, ['skip_classes' => ['My\\Payment\\Logger']]);</code>

Из чанка или Fenom:
<code>[[!mxLogger? &tags=`cart,purchase` &level=`info` &message=`Товар добавлен`]]</code>

<blockquote>Тэги — только латиница и цифры в нижнем регистре, без пробелов; лишние символы вырезаются. Уровни: «debug», «info», «warning», «error».</blockquote>

<strong>Что пишется автоматически</strong>
<ul>
<li>Источник вызова — класс, метод, файл, строка. Движок пропускает кадры диспетчера событий, поэтому для плагинов показывает реальный метод (например msCartHandler::add), а не <s>служебный include</s>.</li>
<li>Пользователь (id), сессия, ip, время.</li>
<li>Режим захвата трассировки — настройка mxlogger.capture_mode со значениями «off», «caller», «full», «auto» (по умолчанию: caller для info, full для warning и error).</li>
</ul>

<strong>Ограничение записи (фильтры)</strong>
По умолчанию пишутся все запросы. Чтобы логировать только нужную цель (отладка на проде без флуда) — задайте любую из настроек:
<ul>
<li>mxlogger.filter_user — id или username через запятую;</li>
<li>mxlogger.filter_usergroup — id или имя группы;</li>
<li>mxlogger.filter_session — идентификатор(ы) сессии;</li>
<li>mxlogger.filter_cookie — «имя» либо «имя=значение».</li>
</ul>
Если задан хотя бы один фильтр — запись идёт только при совпадении.

<strong>События (для уведомлений и интеграций)</strong>
При каждой записи лога вызываются два системных события — на них можно вешать плагины (например уведомления):
<ul>
<li>«mxlOnBeforeLogSave» — ДО записи. В параметры передаются все поля будущей записи (tags, level, message, context, class, function, file, line, user_id, session_id, ip, createdon) и tags_list (массив тэгов). Плагин может отменить запись или изменить любое поле.</li>
<li>«mxlOnAfterLogSave» — ПОСЛЕ записи. Дополнительно передаётся id сохранённой записи. Удобно для нотификаций (письмо/мессенджер при error и т.п.).</li>
</ul>
Отменить запись из обработчика «mxlOnBeforeLogSave»:
<code>$modx->event->returnedValues['prevent'] = true;</code>
Изменить поле (например уровень) из «mxlOnBeforeLogSave»:
<code>$modx->event->returnedValues['level'] = 'error';</code>
Ошибки в обработчиках событий перехватываются и не ломают ни запись лога, ни сам запрос.

<strong>Ротация (автоудаление старых логов)</strong>
Плагин mxLoggerRotate на событии OnMODXInit удаляет записи старше mxlogger.log_lifetime (по умолчанию 604800 секунд = неделя; 0 — не удалять). Реальная чистка выполняется не на каждом запросе, а не чаще раза в mxlogger.rotate_interval секунд (по умолчанию 3600 = раз в час). Удаление идёт порциями с ограничением за один проход, чтобы большой объём не нагружал сайт — остаток дочищается на следующих запусках.

<strong>Доступ к логам в обход MODX</strong>
Если MODX не загружается, логи можно смотреть скриптом по пути assets/components/mxlogger/standalone.php — он <u>не запускает MODX</u>, а читает таблицу напрямую через PDO (параметры БД берёт из core/config/config.inc.php).

<strong>CLI</strong> — по SSH, без ключа. Запускайте интерпретатором PHP 7.4, например:
<code>php assets/components/mxlogger/standalone.php limit=20</code>

Параметры (одинаковы для CLI и WEB): tag, level, process, ident, q, since, until, limit, id, full, color. Примеры:
<ul>
<li>tag=cart — по тэгу;</li>
<li>level=error — по уровню;</li>
<li>process=ms_xxxx — вся воронка процесса;</li>
<li>ident=admin — по пользователю, сессии или ip;</li>
<li>q=текст — поиск по сообщению, источнику, файлу;</li>
<li>since="2026-06-01 10:00" и until="2026-06-01 18:00" — диапазон дат;</li>
<li>limit=50 — число строк (по умолчанию 100, максимум 2000);</li>
<li>id=255 — показать одну запись целиком (context и trace);</li>
<li>full=1 — не усекать context/trace; color=1 или color=0 — форс цвета.</li>
</ul>
Список выводится таблицей. Чтобы развернуть запись — возьмите её номер из колонки ID и добавьте параметр id с этим номером.

<strong>WEB</strong> — нужен ключ, иначе 403. Откройте в браузере адрес скрипта с параметром key и, при необходимости, фильтрами, например: …/assets/components/mxlogger/standalone.php?key=ВАШ_КЛЮЧ&tag=cart&level=error

<strong>Как создать ключ для веб-доступа</strong>
Веб-просмотрщик закрыт, пока не задан ключ. Задайте его одним из способов:
<ol>
<li>Файлом: создайте файл по пути core/components/mxlogger/standalone.key и впишите одну строку — ваш секрет. Сгенерировать секрет можно командой:
<code>openssl rand -hex 20</code>
Каталог core не отдаётся вебом, поэтому ключ снаружи не прочитать.</li>
<li>Переменной окружения MXLOGGER_TOKEN — предпочтительнее, ключ не светится в URL и логах сервера.</li>
</ol>
После этого открывайте адрес скрипта с параметром key. Чтобы выключить веб-доступ — удалите файл ключа (CLI продолжит работать). Чтобы сменить ключ — перезапишите файл новым значением.
<blockquote>Ссылка с ключом = доступ к логам (там сессии, ip, контекст). Не публикуйте её и передавайте только по защищённым каналам.</blockquote>

Если повреждён и сам файл core/config/config.inc.php — параметры БД можно задать переменными окружения MXLOGGER_DSN, MXLOGGER_DB_USER, MXLOGGER_DB_PASS, MXLOGGER_TABLE_PREFIX.

<strong>Системные настройки</strong>
<ul>
<li>mxlogger.enabled — глобальный выключатель записи;</li>
<li>mxlogger.min_level — минимальный уровень;</li>
<li>mxlogger.capture_mode, mxlogger.trace_limit, mxlogger.args_max_depth, mxlogger.args_max_string, mxlogger.args_max_items — захват трассировки;</li>
<li>mxlogger.tag_filter_mode — значения «auto», «fulltext», «like»;</li>
<li>mxlogger.log_lifetime — срок хранения в секундах (0 — не удалять).</li>
</ul>

<strong>Полезные ссылки</strong>
<ul>
<li><a href="https://docs.modx.com/3.x/en/extending-modx/services">MODX: сервисы и getService</a></li>
<li><a href="https://modstore.pro/packages/ecommerce/minishop2">miniShop2 на modstore</a></li>
</ul>

<strong>Лицензия</strong> — GNU GPL v2 или новее.
