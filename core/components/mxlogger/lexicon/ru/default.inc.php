<?php
/**
 * Русский лексикон mxLogger.
 *
 * @package mxlogger
 * @subpackage lexicon
 */
$_lang['mxlogger'] = 'mxLogger — журнал процессов';
$_lang['mxlogger_log'] = 'Запись лога';
$_lang['mxlogger_menu_desc'] = 'Просмотр логов процессов';

$_lang['mxlogger_tab_log'] = 'Журнал';
$_lang['mxlogger_log_intro'] = 'Логи процессов. Используйте фильтр по тэгу, чтобы найти все записи (например «purchase» или «cart»). У записи может быть несколько тэгов. Тэги — lowercase, латиница и цифры. Записи отсортированы по времени и порядку.';
$_lang['mxlogger_guest'] = 'Гость';
$_lang['mxlogger_view'] = 'Подробнее';

/* Колонки */
$_lang['mxlogger_col_createdon'] = 'Время';
$_lang['mxlogger_col_level'] = 'Уровень';
$_lang['mxlogger_col_tags'] = 'Тэги';
$_lang['mxlogger_col_process'] = 'Процесс';
$_lang['mxlogger_col_message'] = 'Сообщение';
$_lang['mxlogger_col_caller'] = 'Источник';
$_lang['mxlogger_col_user'] = 'Пользователь';

/* Поля окна детали */
$_lang['mxlogger_field_source'] = 'Файл:строка';
$_lang['mxlogger_field_session'] = 'Сессия';
$_lang['mxlogger_field_ip'] = 'IP';
$_lang['mxlogger_field_context'] = 'Контекст';
$_lang['mxlogger_field_trace'] = 'Стэк и параметры';
$_lang['mxlogger_no_data'] = 'Нет данных';
$_lang['mxlogger_click_filter'] = 'Кликните, чтобы отфильтровать';

/* Фильтры */
$_lang['mxlogger_filter_tag'] = 'Тэги';
$_lang['mxlogger_tags_empty'] = 'Тэгов пока нет';
$_lang['mxlogger_filter_level'] = 'Уровень…';
$_lang['mxlogger_filter_process'] = 'Process UID…';
$_lang['mxlogger_filter_ident'] = 'Пользователь / сессия / IP…';
$_lang['mxlogger_filter_date_from'] = 'С даты';
$_lang['mxlogger_filter_date_to'] = 'По дату';
$_lang['mxlogger_search'] = 'Поиск по тексту…';

/* Кнопки */
$_lang['mxlogger_btn_refresh'] = 'Обновить';
$_lang['mxlogger_btn_reset'] = 'Сбросить';
$_lang['mxlogger_btn_clear'] = 'Очистить';
$_lang['mxlogger_log_clear_confirm'] = 'Удалить записи журнала по текущему фильтру? Действие необратимо.';
$_lang['mxlogger_log_cleared'] = 'Удалено записей: [[+count]].';

/* Настройки */
$_lang['setting_mxlogger.enabled'] = 'Включить логирование';
$_lang['setting_mxlogger.enabled_desc'] = 'Глобальный выключатель записи логов.';
$_lang['setting_mxlogger.min_level'] = 'Минимальный уровень';
$_lang['setting_mxlogger.min_level_desc'] = 'Записи ниже этого уровня игнорируются: debug, info, warning, error.';
$_lang['setting_mxlogger.tag_filter_mode'] = 'Режим фильтрации по тэгам';
$_lang['setting_mxlogger.tag_filter_mode_desc'] = 'auto — FULLTEXT, а для коротких тэгов (короче 3 символов) — LIKE; fulltext — всегда FULLTEXT (быстро, но короткие тэги не находятся); like — всегда LIKE (медленнее на больших объёмах, но всегда точно).';
$_lang['setting_mxlogger.capture_mode'] = 'Режим захвата трассировки';
$_lang['setting_mxlogger.capture_mode_desc'] = 'off — не собирать; caller — только класс/функция/файл/строка; full — со стэком и параметрами; auto — caller, а для warning/error — full.';
$_lang['setting_mxlogger.trace_limit'] = 'Глубина стэка';
$_lang['setting_mxlogger.trace_limit_desc'] = 'Максимальное число кадров стэка в trace.';
$_lang['setting_mxlogger.args_max_depth'] = 'Глубина параметров';
$_lang['setting_mxlogger.args_max_depth_desc'] = 'Максимальная глубина рекурсии при сериализации аргументов.';
$_lang['setting_mxlogger.args_max_string'] = 'Длина строки параметра';
$_lang['setting_mxlogger.args_max_string_desc'] = 'Строки длиннее обрезаются.';
$_lang['setting_mxlogger.args_max_items'] = 'Элементов массива параметра';
$_lang['setting_mxlogger.args_max_items_desc'] = 'Максимальное число элементов массива при сериализации аргументов.';
$_lang['setting_mxlogger.filter_user'] = 'Фильтр: пользователи';
$_lang['setting_mxlogger.filter_user_desc'] = 'Если задано — логировать только этих пользователей (id или username через запятую).';
$_lang['setting_mxlogger.filter_usergroup'] = 'Фильтр: группы';
$_lang['setting_mxlogger.filter_usergroup_desc'] = 'Если задано — логировать только членов этих групп (id или имя через запятую).';
$_lang['setting_mxlogger.filter_session'] = 'Фильтр: сессии';
$_lang['setting_mxlogger.filter_session_desc'] = 'Если задано — логировать только эти идентификаторы сессий (через запятую).';
$_lang['setting_mxlogger.filter_cookie'] = 'Фильтр: куки';
$_lang['setting_mxlogger.filter_cookie_desc'] = 'Если задано — логировать только при наличии куки. Формат: «имя» или «имя=значение», несколько через запятую.';
$_lang['setting_mxlogger.log_lifetime'] = 'Срок хранения логов (сек)';
$_lang['setting_mxlogger.log_lifetime_desc'] = 'Записи старше указанного числа секунд удаляются плагином ротации (mxLoggerRotate). 0 — не удалять.';
$_lang['setting_mxlogger.rotate_interval'] = 'Интервал ротации (сек)';
$_lang['setting_mxlogger.rotate_interval_desc'] = 'Как часто плагин ротации реально чистит старые записи (троттлинг). По умолчанию 3600 (раз в час).';
