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
 * CLI скрипт для трансформации события из журнала Moodle в xAPI JSON-объект
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use logstore_xapi\local\log_event;

use function \src\transformer\handler;

define('CLI_SCRIPT', true);


require dirname(__DIR__, 6) . '/config.php';
require_once $CFG->libdir . '/clilib.php';
require_once dirname(__DIR__) . '/src/autoload.php';

class xapi_printer {
    const DEST_FILE = 'DEST_FILE';
    const DEST_STD_OUT = 'DEST_STD_OUT';

    protected $ispretty = false;
    protected $outputfilepath = null;
    protected $dest = false;

    public function __construct() {
        $this->dest = static::DEST_STD_OUT;
    }

    public function set_output_file($path) {
        $this->outputfilepath = $path;
        return $this;
    }

    public function set_destination($dest) {
        $this->dest = $dest;
        return $this;
    }

    public function enable_pretty_print() {
        $this->ispretty = true;
        return $this;
    }

    /**
     * @param log_event $logevent
     * @return string
     */
    public function transform(log_event $logevent) {
        global $CFG;
        global $DB;

        $pluginrelease = core_plugin_manager::instance()
            ->get_plugin_info('logstore_xapi')
            ->release;
        $logerror = function ($message = '') {
            if (!PHPUNIT_TEST) {
                error_log(sprintf('[LOGSTORE_XAPI][ERROR] %s', $message));
                debugging($message, DEBUG_NORMAL);
            }
        };
        $loginfo = function ($message = '') {
            if (!PHPUNIT_TEST) {
                debugging($message, DEBUG_DEVELOPER);
            }
        };
        $config = [
            'log_error' => $logerror,
            'log_info' => $loginfo,
            'source_url' => 'http://moodle.org',
            'source_name' => 'Moodle',
            'source_version' => $CFG->release,
            'source_lang' => 'en',
            'send_mbox' => get_config('logstore_xapi', 'mbox'),
            'send_response_choices' => get_config('logstore_xapi', 'sendresponsechoices'),
            'send_short_course_id' => get_config('logstore_xapi', 'shortcourseid'),
            'send_course_and_module_idnumber' => get_config('logstore_xapi', 'sendidnumber'),
            'send_username' => get_config('logstore_xapi', 'send_username'),
            'plugin_url' => 'https://github.com/xAPI-vle/moodle-logstore_xapi',
            'plugin_version' => $pluginrelease,
            'repo' => new \src\transformer\repos\MoodleRepository($DB),
            'app_url' => $CFG->wwwroot,
        ];
        $transformed = handler($config, [$logevent]);
        $transformed = reset($transformed);
        if (false === isset($transformed['transformed']) || false === $transformed['transformed']) {
            $e = (isset($transformed['error'])) ? $transformed['error'] : new Exception('Transform failed');
        }
        return $transformed['statements'];
    }

    public function out(stdClass $logrecord) {
        try {
            $logevent = new log_event($logrecord);
            $result = $this->transform($logevent);
        } catch (moodle_exception $e) {
            cli_error($e->getMessage());
            exit(7);
        }
        if (!$result) {
            cli_error('Empty result');
            exit(7);
        }
        $args = [$result];
        if ($this->ispretty) {
            $args[] = JSON_PRETTY_PRINT;
        }
        $jsonedresult = call_user_func_array('json_encode', $args);

        if ($this->dest === static::DEST_FILE) {
            if (!is_writable($this->outputfilepath)) {
                cli_error('File not writeable');
                exit(5);
            }
            file_put_contents($this->outputfilepath, $jsonedresult);
            return true;
        }
        cli_writeln($jsonedresult);
    }

    /**
     *
     *
     */
    public function dest() {
        return $this->dest;
    }
}
$scriptname = 'transform_log_event.php';
$usage = "Возвращает JSON-объект в формате xAPI представляющий событие журнала.

Событие ищется в стандартном журнале Moodle.

Применение:
    # php {$scriptname} --id=<logid>
    # php {$scriptname} --id=<logid> --out=<path>
    # php {$scriptname} --id=<logid> --out=<path> --pretty
    # php {$scriptname} [--help|-h]

Options:
    -h --help       Вывод текущей справки.
    --id=<logid>    id записи из таблицы логов Moodle
    --out=<path>    путь к файлу куда нужно сохранить вывод
    --pretty        Вывод следа форматированием (не в одну строку)

Examples:

    # php {$scriptname} --id=1234567 --out=/tmp/dump_logevent_1234567_xapi.json --pretty
";

list($options, $unrecognised) = cli_get_params([
    'help'   => false,
    'id'     => null,
    'out'    => null,
    'pretty' => false,
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
if (null === $options['id']) {
    cli_error('id is required');
    exit(3);
}

/** @var moodle_database $DB */
$logrecord = $DB->get_record('logstore_standard_log', ['id' => $options['id']]);

if (!$logrecord) {
    cli_error('Log record not found');
    exit(4);
}

// Выполнить преобразование
$printer = new xapi_printer();

if ($options['out']) {
    $printer->set_destination(xapi_printer::DEST_FILE);
    $printer->set_output_file($options['out']);
}

if ($options['pretty']) {
    $printer->enable_pretty_print();
}

$printer->out($logrecord);

if ($printer->dest() === xapi_printer::DEST_FILE) {
    cli_writeln('Done');
}
