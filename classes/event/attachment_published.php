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
 * Артефакт обучения был успешно загружен в S3.
 *
 * @package    logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\event;

/**
 * Event класс события attachment_published.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int instanceid: Id of instance.
 *      - int roleid: Role id for whom report is viewed.
 *      - int groupid: (optional) group id.
 *      - int timefrom: (optional) time from which report is viewed.
 *      - string action: (optional) action viewed.
 * }
 */
class attachment_published extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('attachment_published_event', 'logstore_xapi');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('', []);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (empty($this->other[''])) {
            throw new \coding_exception('');
        }
    }

    public static function get_other_mapping() {
        $othermapped = [];
        $othermapped['cmid'] = array('db' => 'course_modules', 'restore' => 'course_module');

        return $othermapped;
    }
}
