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
 * Задание по расписанию для отправки артефакторв обучения в S3
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\task;

use logstore_xapi\local\publish_attachments_batch_job;
use logstore_xapi\local\queue_service;
use logstore_xapi\local\u2035_s3client;
use moodle_exception;
use Throwable;

class publish_attachment extends \core\task\scheduled_task {

    const DEFAULT_BATCH_SIZE = 1000;

    /**
     * @inheritdoc
     */
    public function get_name() {
        return get_string('publish_attachment_task_name', 'logstore_xapi');
    }

    /**
     * @inheritdoc
     */
    public function execute() {
        global $DB;

        $batchsize = get_config('logstore_xapi', 'maxbatchsize_s3');
        if (false === $batchsize) {
            $batchsize = static::DEFAULT_BATCH_SIZE;
        }
        $queueservice = queue_service::instance();

        try {
            mtrace(sprintf('Gettig items from queue %s...', queue_service::QUEUE_EMIT_STATEMENTS));
            $records = $queueservice->pop($batchsize, queue_service::QUEUE_PUBLISH_ATTACHMENTS);
            if ([] === $records) {
                mtrace(__CLASS__ .': There are no log records to sent. Stopping.');
                return;
            }
            $s3client = u2035_s3client::build();
            $batchjob = new publish_attachments_batch_job($records, $DB, $s3client);
            $batchjob->run();
            $completeditems = $batchjob->result_success();
            if ([] !== $completeditems) {
                $queueservice->complete($completeditems);
            }
            $itemswitherror = $batchjob->result_error();
            if ([] !== $itemswitherror) {
                $queueservice->requeue($itemswitherror);
            }
            mtrace(sprintf('-- Completed %d; Has errors %d', count($completeditems), count($itemswitherror)));
        } catch (moodle_exception $e) {
            mtrace('Exception was thrown. More info in logs');
            $errmsg = sprintf('[LOGSTORE_XAPI][ERROR] %s %s debug: %s trace: %s', static::class, $e->getMessage(), $e->debuginfo, $e->getTraceAsString());
            error_log($errmsg);
            debugging($errmsg, DEBUG_DEVELOPER);
            $queueservice->requeue($records);
        } catch (Throwable $e) {
            mtrace('Exception was thrown. More info in logs');
            $errmsg = sprintf('[LOGSTORE_XAPI][ERROR] %s trace: %s', static::class, $e->getMessage(), $e->getTraceAsString());
            error_log($errmsg);
            debugging($errmsg, DEBUG_DEVELOPER);
            $queueservice->requeue($records);
        }

    }
}
