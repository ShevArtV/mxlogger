<?php
/**
 * English lexicon for mxLogger.
 *
 * @package mxlogger
 * @subpackage lexicon
 */
$_lang['mxlogger'] = 'mxLogger — process journal';
$_lang['mxlogger_log'] = 'Log entry';
$_lang['mxlogger_menu_desc'] = 'View process logs';

$_lang['mxlogger_tab_log'] = 'Journal';
$_lang['mxlogger_log_intro'] = 'Process logs. Use the tag filter to find all entries (e.g. «purchase» or «cart»). An entry may have several tags. Tags are lowercase, latin and digits. Entries are sorted by time and order.';
$_lang['mxlogger_guest'] = 'Guest';
$_lang['mxlogger_view'] = 'Details';

/* Columns */
$_lang['mxlogger_col_createdon'] = 'Time';
$_lang['mxlogger_col_level'] = 'Level';
$_lang['mxlogger_col_tags'] = 'Tags';
$_lang['mxlogger_col_process'] = 'Process';
$_lang['mxlogger_col_message'] = 'Message';
$_lang['mxlogger_col_caller'] = 'Source';
$_lang['mxlogger_col_user'] = 'User';

/* Detail window fields */
$_lang['mxlogger_field_source'] = 'File:line';
$_lang['mxlogger_field_session'] = 'Session';
$_lang['mxlogger_field_ip'] = 'IP';
$_lang['mxlogger_field_context'] = 'Context';
$_lang['mxlogger_field_trace'] = 'Stack & parameters';
$_lang['mxlogger_no_data'] = 'No data';
$_lang['mxlogger_click_filter'] = 'Click to filter';

/* Filters */
$_lang['mxlogger_filter_tag'] = 'Tags';
$_lang['mxlogger_tags_empty'] = 'No tags yet';
$_lang['mxlogger_filter_level'] = 'Level…';
$_lang['mxlogger_filter_process'] = 'Process UID…';
$_lang['mxlogger_filter_ident'] = 'User / session / IP…';
$_lang['mxlogger_filter_date_from'] = 'From date';
$_lang['mxlogger_filter_date_to'] = 'To date';
$_lang['mxlogger_search'] = 'Search text…';

/* Buttons */
$_lang['mxlogger_btn_refresh'] = 'Refresh';
$_lang['mxlogger_btn_reset'] = 'Reset';
$_lang['mxlogger_btn_clear'] = 'Clear';
$_lang['mxlogger_log_clear_confirm'] = 'Delete journal entries matching the current filter? This cannot be undone.';
$_lang['mxlogger_log_cleared'] = 'Entries removed: [[+count]].';

/* Settings */
$_lang['setting_mxlogger.enabled'] = 'Enable logging';
$_lang['setting_mxlogger.enabled_desc'] = 'Global switch for writing logs.';
$_lang['setting_mxlogger.min_level'] = 'Minimum level';
$_lang['setting_mxlogger.min_level_desc'] = 'Entries below this level are ignored: debug, info, warning, error.';
$_lang['setting_mxlogger.tag_filter_mode'] = 'Tag filter mode';
$_lang['setting_mxlogger.tag_filter_mode_desc'] = 'auto — FULLTEXT, and LIKE for short tags (under 3 chars); fulltext — always FULLTEXT (fast, but short tags are not found); like — always LIKE (slower on large volumes, but always exact).';
$_lang['setting_mxlogger.capture_mode'] = 'Trace capture mode';
$_lang['setting_mxlogger.capture_mode_desc'] = 'off — none; caller — class/function/file/line only; full — with stack and parameters; auto — caller, and full for warning/error.';
$_lang['setting_mxlogger.trace_limit'] = 'Stack depth';
$_lang['setting_mxlogger.trace_limit_desc'] = 'Maximum number of stack frames stored in trace.';
$_lang['setting_mxlogger.args_max_depth'] = 'Parameters depth';
$_lang['setting_mxlogger.args_max_depth_desc'] = 'Maximum recursion depth when serializing arguments.';
$_lang['setting_mxlogger.args_max_string'] = 'Parameter string length';
$_lang['setting_mxlogger.args_max_string_desc'] = 'Longer strings are truncated.';
$_lang['setting_mxlogger.args_max_items'] = 'Parameter array items';
$_lang['setting_mxlogger.args_max_items_desc'] = 'Maximum number of array items when serializing arguments.';
$_lang['setting_mxlogger.filter_user'] = 'Filter: users';
$_lang['setting_mxlogger.filter_user_desc'] = 'If set — log only these users (id or username, comma-separated).';
$_lang['setting_mxlogger.filter_usergroup'] = 'Filter: groups';
$_lang['setting_mxlogger.filter_usergroup_desc'] = 'If set — log only members of these groups (id or name, comma-separated).';
$_lang['setting_mxlogger.filter_session'] = 'Filter: sessions';
$_lang['setting_mxlogger.filter_session_desc'] = 'If set — log only these session ids (comma-separated).';
$_lang['setting_mxlogger.filter_cookie'] = 'Filter: cookies';
$_lang['setting_mxlogger.filter_cookie_desc'] = 'If set — log only when a cookie is present. Format: «name» or «name=value», comma-separated.';
$_lang['setting_mxlogger.log_lifetime'] = 'Log lifetime (sec)';
$_lang['setting_mxlogger.log_lifetime_desc'] = 'Entries older than this number of seconds are removed by the rotation plugin (mxLoggerRotate). 0 — keep forever.';
$_lang['setting_mxlogger.rotate_interval'] = 'Rotation interval (sec)';
$_lang['setting_mxlogger.rotate_interval_desc'] = 'How often the rotation plugin actually purges old entries (throttling). Default 3600 (hourly).';
