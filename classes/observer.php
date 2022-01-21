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
 * Observer класс
 *
 * @package    logstore_xapi
 * @copyright  2022 academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\event;

use logstore_xapi\event\attachment_published;
use logstore_xapi\local\persistent\queue_item;
use logstore_xapi\local\queue_service;

/**
 * Observer класс
 *
 */
class observer {

    /**
     * Обработает событие публикации артефакта обучения в S3
     *
     * @param attachment_published $event
     *
     * @return void
     */
    public static function attachment_published(attachment_published $event) {
        $queueservice = queue_service::instance();
        $xapievent = $event->get_record_snapshot('logstore_xapi_log', $event->other['logrecordid']);
        $queueservice->push($xapievent, queue_service::QUEUE_EMIT_STATEMENTS);
    }

}

