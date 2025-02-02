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
 * Содержит класс для работы с очердью задачь
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\local;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use logstore_xapi\event\queue_item_banned;
use logstore_xapi\event\queue_item_completed;
use logstore_xapi\event\queue_item_requeued;
use logstore_xapi\local\persistent\queue_item;
use moodle_database;
use moodle_exception;
use Throwable;

class queue_service {

    const DEFAULT_ATTEMPTS_LIMIT = 7;
    const QUEUE_EMIT_STATEMENTS = 'EMIT_STATEMENTS';
    const QUEUE_PUBLISH_ATTACHMENTS = 'PUBLISH_ATTACHMENTS';

    /**
     * @var moodle_database
     */
    protected $db;

    /**
     * @param moodle_database $db
     *
     */
    public function __construct(moodle_database $db) {
        $this->db = $db;
    }

    /**
     * Создаств собственный экземпляр класса
     * @return self
     */
    public static function instance() {
        global $DB;

        $instance = new self($DB);

        return $instance;
    }

    /**
     * @param int $limit
     * @oaran string|null $queue
     * @return queue_item[]
     *
     */
    public function pop($limit = 1, $queue = null) {
        global $USER;

        // Берём из очереди свежие записи
        $conditions = ['isbanned' => false, 'isrunning' => false];
        if ($queue) {
            $conditions['queue'] = $queue;
        }
        $sort = 'priority ASC, timecreated ASC, timecompleted ASC';
        $items = queue_item::get_records(
            $conditions,
            $sort,
            '', // $order
            0, // $skip
            $limit
        );
        if ([] === $items) {
            return [];
        }

        // Помечаем их - "В обработке"
        list($insql, $inparams) = $this->db->get_in_or_equal(
            array_map(function(queue_item $r) {return $r->get('id');}, $items)
        );
        $insql = 'id ' . $insql;
        $inparams = $inparams;

        $newfieldvalue = [
            'isrunning'    => true,
            'timestarted'  => time(),
            'timemodified' => time(),
            'usermodified' => $USER->id,
        ];
        foreach($newfieldvalue as $newfield => $newvalue) {
            $this->db->set_field_select(queue_item::TABLE, $newfield, $newvalue, $insql, $inparams);
        }

        return queue_item::get_records_select($insql, $inparams);
    }

    /**
     * @param log_event $events
     * @param string                $queuename
     * @param array                 $payload
     *
     * @return queue_item
     */
    public function push(log_event $event, string $queuename, $payload = []) {
        $this->validate_queuename($queuename);

        $qitem = new queue_item(0, (object) [
            'logrecordid' => $event->id,
            'itemkey' => $this->make_item_key($event, $queuename),
            'queue' => $queuename,
            'payload' => json_encode($payload),
        ]);

        $qitem->save();

        return $qitem;
    }

    /**
     * @param queue_item[] $queueitems
     *
     * @return void
     */
    public function complete(array $queueitems) {
        global $USER;

        if ([] === $queueitems) {
            return;
        }

        $toremove = [];
        $toban = [];
        /** @var queue_item $qitem */
        foreach ($queueitems as $qitem) {
            if ($qitem->get('isbanned')) {
                $toban[] = $qitem;
            } else {
                $toremove[$qitem->get('id')] = $qitem;
            }
        }
        list($insql, $inparams) = $this->db->get_in_or_equal(array_keys($toremove));
        $insql = 'id ' . $insql;
        $inparams = $inparams;
        $this->db->delete_records_select(queue_item::TABLE, $insql, $inparams);
        array_walk($toremove, function (queue_item $qi) {
            $event = queue_item_completed::create_from_record($qi);
            $event->trigger();
        });

        /** @var queue_item $qitem */
        foreach($toban as $qitem) {
            $qitem
                ->increase_attempt_number()
                ->mark_as_complete()
                ->save();
            $event = queue_item_banned::create_from_record($qitem);
            $event->trigger();
        }
    }

    /**
     * @param queue_item[] $queueitems
     *
     * @return void
     */
    public function requeue(array $queueitems) {

        if ([] === $queueitems) {
            return;
        }
        $queueitems = array_map(
            function(queue_item $qi) {
                $qi->mark_as_complete();
                if (false === $qi->get('isbanned')) {
                    $qi->increase_attempt_number();
                }
                return $qi;
            },
            $queueitems
        );

        $attemptslimit = $this->get_attempts_limit();
        $toberequeued = [];
        $tobebanned = [];
        /** @var queue_item $qitem */
        foreach ($queueitems as $qitem) {
            $dbgmsg = sprintf(
                'id: %d isbanned: %d attemptlimit: %d attempts: %d',
                $qitem->get('id'), $qitem->get('isbanned'), $attemptslimit, $qitem->get('attempts')
            );
            if (debugging() && !PHPUNIT_TEST) {
                debugging($dbgmsg, DEBUG_DEVELOPER);
            }
            if (false === $qitem->get('isbanned') && $attemptslimit > $qitem->get('attempts')) {
                $toberequeued[] = $qitem;
                continue;
            }
            // либо задача была заблокирована либо достигнут лимит попыток
            $qitem->mark_as_banned();
            $tobebanned[] = $qitem;
            if (debugging() && !PHPUNIT_TEST) {
                debugging(sprintf('qitem id:%d is banned', $qitem->get('id')), DEBUG_DEVELOPER);
            }
        }

        $toupdate = array_merge($toberequeued, $tobebanned);
        array_walk($toupdate, function (queue_item $qi) {
            if (false === $qi->is_valid()) {
                $msg = sprintf(
                    '%s: Заданы не валидные значения при повторном размещении задачи %d в очередь. errors: %s',
                    static::class, $qi->get('id'), json_encode($qi->get_errors())
                );
                error_log($msg);
            }
            $qi->save();
        });

        array_walk($toberequeued, function (queue_item $qi) {
            $event = queue_item_requeued::create_from_record($qi);
            $event->trigger();
        });

        array_walk($tobebanned, function (queue_item $qi) {
            $event = queue_item_banned::create_from_record($qi);
            $event->trigger();
        });
    }

    /**
     *
     * @return int
     */
    protected function get_attempts_limit() {
        $limit = get_config('logstore_xapi', 'queueitemattemptslimit');
        if (! $limit) {
            $limit = static::DEFAULT_ATTEMPTS_LIMIT;
        }

        return (int) $limit;
    }

    /**
     * @param string $queuename
     *
     * @throws coding_exception
     *
     * @return bool
     */
    protected function validate_queuename($queuename) {
        $allowedqueuenames = [
            static::QUEUE_EMIT_STATEMENTS,
            static::QUEUE_PUBLISH_ATTACHMENTS
        ];
        if (false === in_array($queuename, $allowedqueuenames)) {
            throw new coding_exception('Данная очердь не обрабатывается ' . $queuename);
        }
        return true;
    }

    /**
     * @param log_event $event
     * @param string $queuename
     *
     * @return string
     */
    protected function make_item_key(log_event $event, string $queuename) {
        return sprintf(
            '%s:%s:%d',
            $queuename,
            str_replace('\\', '_', $event->eventname),
            $event->id
        );
    }
}
