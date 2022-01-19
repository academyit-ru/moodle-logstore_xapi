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

use core_plugin_manager;

class emit_statements_batch_job extends base_batch_job {

    /**
     * @var array
     */
    protected $records;

    /**
     * @var array
     */
    protected $resulterror;

    /**
     * @var array
     */
    protected $resultsuccess;

    /**
     *
     *
     */
    public function __consturct(array $records) {
        $this->records = $records;
        $this->resulterror = [];
        $this->resultsuccess = [];
    }

    /**
     *
     */
    public function result_success() {
        return [];
    }

    /**
     *
     */
    public function result_error() {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        global $CFG;

        $pluginrelease = $this->get_plugin_release();
        $logerror = function ($message = '') {
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
                'loader' => 'moodle_curl_lrs',
                'lrs_endpoint' => $this->get_config('endpoint', ''), 'lrs_token' => $this->get_config('token', ''),
                'lrs_max_batch_size' => $this->get_config('maxbatchsize', 150),
            ],
        ];
        $loadedevents = \src\handler($handlerconfig, $this->records);

        $this->resulterror = $this->filter_failed_statements($loadedevents);
        $this->resultsuccess  = $this->filter_registered_staetments($loadedevents);
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
    protected function get_config($name, $default = null) {
        $value = get_config('logstore_xapi', $name);
        if (!$value) {
            return $default;
        }
        return $value;
    }

    /**
     * Фильтрует записи которые не удалось зарегистрировать в LRS
     *
     * @param array $loadedevents
     *
     * @return array
     */
    protected function filter_failed_statements(array $loadedevents) {
        $filtered = array_filter($loadedevents, fn($event) => $event['loaded'] === false);

        return array_map(fn($ev) => $ev['event'], $filtered);
    }

    /**
     * Фильтрует записи которые были зарегистрированы в LRS
     *
     * @param array $loadedevents
     *
     * @return array
     */
    protected function filter_registered_staetments(array $loadedevent) {
        $filtered = array_filter($loadedevent, fn($event) => $event['loaded'] === true);

        return array_map(fn($ev) => $ev['event'], $filtered);
    }
}
