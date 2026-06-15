<?php
/**
 * Запись лога mxLogger.
 *
 * @property string $tags        Тэги процесса в CSV-обёртке: ,cart,purchase, (lowercase, [a-z0-9]).
 * @property string $process_uid Идентификатор экземпляра процесса.
 * @property string $level       Уровень: debug|info|warning|error.
 * @property string $message     Текст сообщения.
 * @property array  $context     Произвольные структурированные данные (JSON).
 * @property string $class       Класс вызывающего кода.
 * @property string $function    Метод/функция вызывающего кода.
 * @property string $file        Файл, из которого сделан вызов лога.
 * @property int    $line        Строка вызова лога.
 * @property array  $trace       Стэк вызовов и параметры (JSON).
 * @property int    $user_id     ID пользователя MODX (0 — гость).
 * @property string $session_id  Идентификатор сессии.
 * @property string $ip          IP-адрес.
 * @property int    $createdon   Время записи (unix timestamp).
 *
 * @package mxlogger
 */
class mxLoggerLog extends xPDOSimpleObject {}
