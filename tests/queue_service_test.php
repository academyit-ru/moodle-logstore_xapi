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
 * Содержит тесткейс для класса logstore_xapi\local\queue_service.
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi;

use advanced_testcase;
use logstore_xapi\event\queue_item_banned;
use logstore_xapi\event\queue_item_requeued;
use logstore_xapi\local\log_event;
use logstore_xapi\local\persistent\queue_item;
use logstore_xapi\local\queue_service;
use moodle_database;

use function GuzzleHttp\Promise\queue;

require_once dirname(__DIR__) . '/src/autoload.php';

/**
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class queue_service_testcase extends advanced_testcase {
    /**
     * Дано:
     *      - В таблице logstore_xapi_queue есть несколько записей
     *          - часть из них забанены
     *      - Они были помечены "на обработке"
     * Выполнить:
     *      - $qservice->requeue($qitems)
     * Результат:
     *      - У отправленных qitems
     *          - увелчено число attempts
     *          - isrunning = false
     *          - timecompleted = timemodified
     *          - колонка lasterror заполнена
     *      - Для qitem которые превысили количество попыток были забанены isbanned = true
     *      - Для тех записей что не были отобраны
     *          - их число attempts не было увеличено
     */
    public function test_requeue_items() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $datasetbasepath = dirname(__FILE__) . '/fixtures/dataset2';
        $tablename = queue_item::TABLE;
        $dataset = $this->createCsvDataSet([$tablename => "{$datasetbasepath}/{$tablename}.csv"]);
        $this->loadDataSet($dataset);

        $this->assertEquals(2, queue_item::count_records(['isrunning' => true]));

        $qitems = queue_item::get_records(['isrunning' => true]);
        $qitems = array_map(function($qi) {return $qi->set('lasterror', 'FOO BAR BAZ');}, $qitems);

        $sink = $this->redirectEvents();

        $qservice = queue_service::instance();
        $qservice->requeue($qitems);

        $events = $sink->get_events();
        $this->assertCount(2, $events);
        $event = array_shift($events);
        $this->assertInstanceOf(queue_item_requeued::class, $event);
        $event = array_shift($events);
        $this->assertInstanceOf(queue_item_banned::class, $event);

        $this->assertEquals(1, queue_item::count_records(['attempts' => 1]));
        $this->assertEquals(3, queue_item::count_records(['attempts' => queue_service::DEFAULT_ATTEMPTS_LIMIT]));
        $this->assertEquals(0, queue_item::count_records(['isrunning' => true]));

        foreach ($qitems as $qitem) {
            $qitem->read();
            $this->assertTrue($qitem->get('timecompleted') === $qitem->get('timemodified'), "поле timecompleted должно содержать текущую метку времени");
        }
    }

    /**
     * TODO: write docbloc
     *
     *
     */
    public function test_complete_items() {
        # code ...
    }

    /**
     * Дано:
     *      - В таблице logstore_xapi_queue Есть N
     *          - записей к обработке
     *          - заблокированы (isbanned = true)
     *          - со статусом в обработке (isrunning = true)
     *      -
     * Выполнить:
     *      - queue_service::pop()
     * Реузльтат:
     *      - записи отобранные к обработке помечены как запущенные
     *          - isrunning = true
     *          - timestarted = $now
     *          - timemodified = $now
     *          - usermodified = $USER->id
     *      - записи которые уже были запущены не затронуты
     *      - записи у котрых был выставлен статус забанены не затронуты
     */
    public function test_pop_items() {
        /** @var moodle_database $DB */
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $datasetbasepath = dirname(__FILE__) . '/fixtures/dataset1';
        $tablename = queue_item::TABLE;
        $dataset = $this->createCsvDataSet([$tablename => "{$datasetbasepath}/{$tablename}.csv"]);
        $this->loadDataSet($dataset);

        $conditionts = ['queue' => queue_service::QUEUE_EMIT_STATEMENTS, 'isrunning' => false, 'isbanned' => false];
        $this->assertEquals(1, $DB->count_records(queue_item::TABLE, $conditionts));

        $qservice = queue_service::instance();
        $qitems = $qservice->pop(1, queue_service::QUEUE_EMIT_STATEMENTS);

        $this->assertCount(1, $qitems);
        $this->assertEquals(0, $DB->count_records(queue_item::TABLE, $conditionts));

        $qitem = reset($qitems);
        $dbrecord = $DB->get_record(queue_item::TABLE, ['id' => $qitem->get('id')]);

        $this->assertTrue((bool) $dbrecord->isrunning);
        $this->assertTrue((bool) $dbrecord->timestarted);
        $this->assertTrue((bool) $dbrecord->timemodified);
        $this->assertEquals($user->id, $dbrecord->usermodified);
    }

    /**
     * Дано:
     *      - В таблице logstore_xapi_queue нет записей
     * Выполнить:
     *      - $queueservice->push()
     * Результат:
     *      - В таблице logstore_xapi_queue созданы записи
     */
    public function test_push_items() {
        /** @var moodle_database $DB */
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $queueevents = [
            queue_service::QUEUE_EMIT_STATEMENTS => [
                [
                    'logevent' => new log_event((object) ['id' => 1, 'eventname' => 'foo_bar']),
                    'payload' => ['foo' => 'bar']
                ],
            ],
            queue_service::QUEUE_PUBLISH_ATTACHMENTS => [
                [
                    'logevent' => new log_event((object) ['id' => 2, 'eventname' => 'baz_boo']),
                    'payload' => ['baz' => 'boo']
                ],
            ],
        ];

        $qservice = queue_service::instance();
        foreach($queueevents as $queuename => $tuples) {
            foreach ($tuples as $tuple) {
                $qservice->push($tuple['logevent'], $queuename, $tuple['payload']);
            }
        }

        $this->assertEquals(1, queue_item::count_records(['queue' => queue_service::QUEUE_EMIT_STATEMENTS]));
        $this->assertEquals(1, queue_item::count_records(['queue' => queue_service::QUEUE_PUBLISH_ATTACHMENTS]));
    }
}
