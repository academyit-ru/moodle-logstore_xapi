<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CLI скрипт для заполнения журнала logstore_xapi_log из logstore_standard_log
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use logstore_xapi\local\log_event;

define('CLI_SCRIPT', true);

require dirname(__DIR__, 6) . '/config.php';
require_once $CFG->libdir . '/clilib.php';
require_once dirname(__DIR__) . '/src/autoload.php';

$scriptname = basename(__FILE__);;
$usage = "Заполнит журнал logstore_xapi_log из logstore_standard_log при этом отсеит лишние записи.

Лишними записями явлюятся те что не входят в список get_config('logstore_xapi', 'routes') не связаны с курсом из списка get_config('logstore_xapi', 'courses')
и они не связаны с пользователем из УНТИ

Применение:
    # php {$scriptname} --ids=1337,6969,7777
    # php {$scriptname} --since=2022-01-01T00:00:01+05:00
    # php {$scriptname} [--help|-h]

Для корректной работы нужно указать либо --since либо --ids

Options:
    -h --help    Вывод текущей справки.
    --ids        Список id записей из logstore_standard_log через запятую которые нужно скопировать в logstore_xapi_log
    --since      Метка времени в формате для DateTime. Определит дату и время начиная с которой нужно будет копировать события журнала

Examples:

    # php {$scriptname} --since=2022-01-01T00:00:01+05:00
    # php {$scriptname} --ids=1337,6969,7777
";

list($options, $unrecognised) = cli_get_params([
    'help'   => false,
    'ids'     => null,
    'since'     => null,
], [
    'h' => 'help'
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL.'  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(2);
}


// ids имеет приоритет
// Посчитать сколько событий в журнале standard_log соотвествуют условию отбора если 0 то выйти с ошибкой
// Собрать запрос
// Вытащить пачку событий
// Отфильтровать события которые не отвечают требованиям
// Выполнить INSERT

$sincedt = null;
$ids = [];

if (null !== $options['since']) {
    try {
        $sincedt = new DateTimeImmutable($options['since'], core_date::get_server_timezone_object());
    } catch (Throwable $e) {
        cli_error('!!! ОШИБКА: ' . $e->getMessage());
    }
}
if (null !== $options['ids']) {
    $ids = explode(',', $options['ids']);
}

if (null === $sincedt && [] === $ids) {
    cli_error('!!! Не указан один из обязательных параметров');
}
/** @var moodle_database $DB */
$DB;
$eventlist = explode(
    ',', get_config('logstore_xapi', 'routes')
);
$courselist = explode(
    ',', get_config('logstore_xapi', 'courses')
);
list($ineventlistsql, $ineventlistparams) = $DB->get_in_or_equal(
    $eventlist,
    SQL_PARAMS_NAMED,
    'eventname_'
);
list($incourselistsql, $incourselistparams) = $DB->get_in_or_equal(
    $courselist,
    SQL_PARAMS_NAMED,
    'course_'
);

$sql = <<<SQL
    SELECT *
    FROM {logstore_standard_log} l
    WHERE
        l.timecreated >= :since
        AND l.eventname {$ineventlistsql}
        AND l.courseid {$incourselistsql}
        AND (
            l.userid IN (SELECT id FROM {user} WHERE auth LIKE :authtype1 AND deleted = 0)
            OR
            l.relateduserid IN (SELECT id FROM {user} WHERE auth :authtype2 AND deleted = 0)
        )
    SQL;

$params = [
    'ineventlistsql' => $ineventlistparams,
    'incourselistsql' => $incourselistparams,
    'authtype1' => 'untissooauth',
    'authtype2' => 'untissooauth',
];
if ($sincedt) {
$params['since'] = $sincedt->format('U');
}
$SKIPRECORDCHECKS = true;

if ([] !== $ids) {
    list($inidssql, $inidsparams) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ids_');
    $sql = <<<SQL
    SELECT *
    FROM {logstore_standard_log} l
    WHERE
        l.id {$inidssql}
    SQL;
    $params = $inidsparams;
    $SKIPRECORDCHECKS = false;
}

$limit = 1000;
$offset = 0;
$i = 0;
$records = [];
$totalrecordscout = 0;
$insertedrecordscount = 0;
cli_writeln('Начинаю:');
while ($records = $DB->get_records_sql($sql, $params, $offset, $limit)) {
    if ([] === $records) {
        break;
    }
    foreach ($records as $record) {
        $totalrecordscout++;
        if (false === $SKIPRECORDCHECKS) {
            if (false === in_array($record->courseid, $courselist)) {
                $msg = sprintf("    Запись id:%d не относится к курсу из списка get_config('logstore_xapi', 'courses').  Пропускаю.", $record->id);
                cli_writeln($msg, STDERR);
                if (debugging()) {
                    cli_writeln(str_repeat(' ', 8) . json_encode(['courselist' => $courselist, 'record->courseid' => $record->courseid, 'cfg' => get_config('logstore_xapi', 'courses')]));
                }
                continue;
            }
            if (false === in_array($record->eventname, $eventlist)) {
                $msg = sprintf("    Запись id:%d не относится к событиям из списка get_config('logstore_xapi', 'routes'). Пропускаю.", $record->id);
                cli_writeln($msg, STDERR);
                if (debugging()) {
                    cli_writeln(str_repeat(' ', 8) . json_encode(['record->eventname' => $record->eventname]));
                }
                continue;
            }
            list($inrecorduserssql, $inrecordusersparams) = $DB->get_in_or_equal([$record->userid, $record->relateduserid]);
            if (false === $DB->record_exists_select('user', "deleted = 0 AND auth = 'untissooauth' AND id {$inrecorduserssql}", $inrecordusersparams)) {
                $msg = sprintf("    Запись id:%d не связана с пользователем из УНТИ. Пропускаю.", $record->id);
                cli_writeln($msg, STDERR);
                if (debugging()) {
                    cli_writeln(str_repeat(' ', 8) . json_encode(['record->userid' => $record->userid, 'record->relateduserid' => $record->relateduserid]));
                }
                continue;
            }
        }
        $newid = $DB->insert_record(log_event::TABLE, $record, $returnid = true, $bulk = true);
        $msg = sprintf('    Запись id:%d добавлена в %s c id:%d', $record->id, log_event::TABLE, $newid);
        cli_writeln($msg);
        $insertedrecordscount++;
    }
    $offset = ++$i * $limit;
}

$msg = sprintf('Получено записей %d из них скопировано записей %d', $totalrecordscout, $insertedrecordscount);
cli_writeln($msg);
