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
namespace logstore_xapi\local;

use logstore_xapi\event\attachment_published;
use logstore_xapi\local\queue_service;
use moodle_exception;
use Throwable;

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
        try {
            $queueservice = queue_service::instance();
            $logevent = $event->get_record_snapshot('logstore_xapi_log', $event->other['logrecordid']);
            $queueservice->push(new log_event($logevent), queue_service::QUEUE_EMIT_STATEMENTS);
            if (debugging()) {
                error_log(sprintf(
                    '[LOGSTORE_XAPI][DEBUG] %s: Pushed logevent id:%d in to the queue: %s',
                    static::class,
                    $logevent->id,
                    queue_service::QUEUE_EMIT_STATEMENTS
                ));
            }
        } catch (moodle_exception $e) {
            error_log(sprintf(
                '[LOGSTORE_XAPI][ERROR] %s: %s debug: %s',
                static::class,
                $e->getMessage(),
                $e->debuginfo
            ));
        } catch (Throwable $e) {
            error_log(sprintf(
                '[LOGSTORE_XAPI][ERROR] %s: %s ',
                static::class,
                $e->getMessage()
            ));
        }
    }

}

