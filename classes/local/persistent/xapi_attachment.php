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
 * Persistent класс для таблицы logstore_xapi_attachments
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\local\persistent;

defined('MOODLE_INTERNAL') || die();

use core\persistent;

class xapi_attachment extends persistent {

    const TABLE = 'logstore_xapi_attachments';

    /**
     * @inheritdoc
     */
    protected static function define_properties() {
        return [
            'eventid' => [
                'null' => NULL_NOT_ALLOWED,
                'type' => PARAM_INT,
            ],
            's3_url' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            's3_filename' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            's3_sha2' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            's3_filesize' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_INT,
                'default' => '',
            ],
            's3_contenttype' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => '',
            ],
            's3_etag' => [
                'null' => NULL_ALLOWED,
                'type' => PARAM_TEXT,
                'default' => '',
            ],
        ];
    }

    /**
     *
     * @return int
     */
    protected function get_eventid() {
        return (int) $this->raw_get('eventid');
    }

    /**
     *
     * @return int
     */
    protected function get_s3_filesize() {
        return (int) $this->raw_get('s3_filesize');
    }
}
