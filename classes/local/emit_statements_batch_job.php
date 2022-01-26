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
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\local;

require_once dirname(__DIR__, 2) . '/src/autoload.php';

use core_plugin_manager;
use logstore_xapi\local\persistent\queue_item;
use logstore_xapi\local\persistent\xapi_record;
use moodle_database;

class emit_statements_batch_job extends base_batch_job {

    const DEFAULT_MAX_BATCH_SIZE = 150;

    /**
     * @var queue_item[]
     */
    protected $queueitems;

    /**
     * @var queue_item[]
     */
    protected $resulterror;

    /**
     * @var queue_item[]
     */
    protected $resultsuccess;

    /**
     * @var xapi_record[]
     */
    protected $xapirecords;

    /**
     * @var moodle_database
     */
    protected $db;

    /**
     * @var string|callable
     */
    protected $loader;
    /**
     *
     * @param queue_item[] $queueitems,
     * @param moodle_database $db
     */
    public function __construct(array $queueitems, moodle_database $db, $loader = 'moodle_curl_lrs') {
        $this->queueitems = $queueitems;
        $this->resulterror = [];
        $this->resultsuccess = [];
        $this->xapirecords = [];
        $this->events = [];
        $this->db = $db;
        $this->loader = $loader;
    }

    /**
     * @return queue_item[]
     */
    public function result_success() {
        return $this->resultsuccess;
    }

    /**
     * @return queue_item[]
     */
    public function result_error() {
        return $this->resulterror;
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        global $CFG;

        $pluginrelease = $this->get_plugin_release();
        $logerror = function ($message = '') {
            error_log(sprintf('[LOGSTORE_XAPI][ERROR] %s', $message));
            debugging($message, DEBUG_NORMAL);
        };
        $loginfo = function ($message = '') {
            debugging($message, DEBUG_DEVELOPER);
        };
        $handlerconfig = [
            'log_error' => $logerror,
            'log_info' => $loginfo,
            'transformer' => [
                'source_url' => 'http://moodle.org',
                'source_name' => 'Moodle',
                'source_version' => $CFG->release,
                'source_lang' => 'en',
                'send_mbox' => $this->get_config('mbox', false),
                'send_response_choices' => $this->get_config('sendresponsechoices', false),
                'send_short_course_id' => $this->get_config('shortcourseid', false),
                'send_course_and_module_idnumber' => $this->get_config('sendidnumber', false),
                'send_username' => $this->get_config('send_username', false),
                'plugin_url' => 'https://github.com/xAPI-vle/moodle-logstore_xapi',
                'plugin_version' => $pluginrelease,
                'repo' => new \src\transformer\repos\MoodleRepository($this->db),
                'app_url' => $CFG->wwwroot,
            ],
            'loader' => [
                'loader' => $this->get_loader(),
                'lrs_endpoint' => $this->get_config('endpoint', ''),
                'lrs_token' => $this->get_config('token', ''),
                'lrs_max_batch_size' => count($this->queueitems),
            ],
        ];

        mtrace('Start handling log events...');
        $loadedevents = \src\handler($handlerconfig, $this->get_events());
        $failed = $this->filter_failed_statements($loadedevents);
        $registered = $this->filter_registered_staetments($loadedevents);
        mtrace('-- Failed events ' . count($failed));
        mtrace('-- Registered events ' . count($registered));

        $errortuples = $this->map_queueitems_with_loadedevent($failed);
        $this->resulterror = array_map(function($tuple) {
            /** @var queue_item $qitem */
            list($qitem, $loadedevent) = $tuple;
            $errormsg = $loadedevent['error'] ?? '!!! EMPTY ERROR MESSAGE !!!';
            $qitem->set('lasterror', $errormsg);

            return $qitem;
        }, $errortuples);

        if (0 < count($registered)) {

            $registeredtuples = $this->map_queueitems_with_loadedevent($registered);

            $xapirecords = [];
            foreach ($registeredtuples as $tuple) {
                /** @var queue_item $qitem */
                list($qitem, $loadedevent) = $tuple;
                $this->resultsuccess[] = $qitem;
                $xapirecords[] = new xapi_record(0, (object) [
                    'lrs_uuid'       => $loadedevent['uuid'],
                    'body'           => json_encode($loadedevent['statements']),
                    'eventid'        => $qitem->get('logrecordid'),
                    'timeregistered' => time()
                ]);
            }

            $this->xapirecords = array_map(
                function (xapi_record $xapirecord) {
                    $xapirecord->save();
                    return $xapirecord;
                },
                $xapirecords
            );

            mtrace('---- xapi_records created ' . count($this->xapirecords));
        }
    }

    /**
     *
     * @return string
     */
    protected function get_plugin_release() {
        return core_plugin_manager::instance()
            ->get_plugin_info('logstore_xapi')
            ->release;
    }

    /**
     * @param string $name
     * @param mixed $default
     *
     * @return string|false
     */
    protected function get_config(string $name, $default = null) {
        $value = get_config('logstore_xapi', $name);
        if (!$value) {
            return $default;
        }
        return $value;
    }

    /**
     * Фильтрует записи которые не удалось зарегистрировать в LRS
     *
     * @param stdClass[] $loadedevents
     *
     * @return mixed[] [
     *      'event' => log_event,
     *      'statement' => string,
     *      'transformed' => bool,
     *      'loaded' => false,
     *      'uuid' => null,
     *      'error' => string
     * ]
     */
    protected function filter_failed_statements(array $loadedevents) {
        return array_filter($loadedevents, function($event) {
            return $event['loaded'] === false;
        });
    }

    /**
     * Фильтрует записи которые были зарегистрированы в LRS
     *
     * @param \stdClass[] $loadedevents
     *
     * @return mixed[] [
     *      'event' => log_event,
     *      'statement' => string,
     *      'transformed' => bool,
     *      'loaded' => true,
     *      'uuid' => string,
     *      'error' => null
     * ]
     */
    protected function filter_registered_staetments(array $loadedevents) {
        return array_filter($loadedevents, function($event) {
            return $event['loaded'] === true;
        });
    }

    /**
     * Отфильтрует список задач по $loadedevents
     *
     * @param log_event[] $loadedevents
     *
     * @return mixed[
     *      [queue_item, $loadedevent]
     * ]
     */
    protected function map_queueitems_with_loadedevent(array $loadedevents) {
        $queueitems = [];
        foreach ($this->queueitems as $qitem) {
            $queueitems[$qitem->get('logrecordid')] = $qitem;
        }
        return array_filter(
            array_map(function ($loadedevent) use ($queueitems) {
                $logrecord = $loadedevent['event'] ?? null;
                if (!array_key_exists($logrecord->id, $queueitems)) {
                    return false;
                }
                return [$queueitems[$logrecord->id], $loadedevent];
            }, $loadedevents)
        );
    }

    /**
     * Если xAPI выражения были успешно зарегистрированы в LRS то будут
     * созданы записи в таблице logstore_xapi_records
     *
     * @return xapi_record[]
     */
    public function get_xapi_records() {
        return $this->xapirecords;
    }

    /**
     *
     * @return string|callable
     */
    public function get_loader() {
        return $this->loader; // = 'moodle_curl_lrs';
    }

    /**
     * @param string|callable $loader
     * @return self
     */
    public function set_loader($loader) {
        $this->loader = $loader;
    }
}
