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

use logstore_xapi\local\u2035_s3client;

define('CLI_SCRIPT', true);

require dirname(__DIR__, 6) . '/config.php';
require_once $CFG->libdir . '/clilib.php';
require_once dirname(__DIR__) . '/src/autoload.php';
require_once dirname(__DIR__) . '/locallib.php';

$scriptname = basename(__FILE__);;
$usage = "
Options:
    -fileid      id файла из таблицы files для тестовой отправки
    -h --help    Вывод текущей справки.

Examples:
    {$scriptname} --fileid=1234
";

list($options, $unrecognised) = cli_get_params([
    'help'   => false,
    'fileid'     => null,
    'filename' => null,
], [
    'h' => 'help',
    'n' => 'filename',
    'i' => 'fileid',
]);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL.'  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'core_admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln($usage);
    exit(2);
}
$fileid = $options['fileid'];
if (!$fileid) {
    cli_error("!!! fileid обязательный параметр");
}

$fs = get_file_storage();
$storedfile = $fs->get_file_by_id($fileid);
$filenaem = isset($options['filename']) ? $options['filename'] : $storedfile->get_filename();

$cfgdump = [
    'awsendpoint' => get_config('logstore_xapi', 's3_endpoint'),
    'awskey'      => get_config('logstore_xapi', 'u2035_aws_key'),
    'awssecret'   => get_config('logstore_xapi', 'u2035_aws_secret'),
    'awsbucket'   => get_config('logstore_xapi', 's3_bucket'),
];
cli_writeln(var_export($cfgdump, true));
exit(69);
$s3client = u2035_s3client::build();

/// 2. Загрузка созданного архива в S3 ///
try {
    $handle = $storedfile->get_content_file_handle();
    $uploadresult = $s3client->upload($filenaem, $handle);
} catch (S3Exception $e) {
    fclose($handle);
    $errormsg = sprintf(
        '%s: При отправке файлов в S3 для события журнала id:%d name:%s возникла ошибка: %s',
        static::class, $logevent->id, $logevent->eventname, $e->getMessage()
    );
    cli_error("!!! " . $errormsg);
}
fclose($handle);
