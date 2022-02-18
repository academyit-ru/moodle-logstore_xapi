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
 * Скрипт для вывода инфомармации из монитора очереди
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once dirname(__DIR__, 6) . '/config.php';

require_login();


$context = context_system::instance();
$pageurl = new moodle_url('/admin/tool/log/store/xapi/queues/monitor.php');
$title = get_string('pagetitle_queues_monitor', 'logstore_xapi');
$heading = $title;

require_capability('logstore/xapi:view_queue_monitor', $context);

/** @var moodle_page $PAGE */
$PAGE->set_context($context);
$PAGE->set_url($pageurl);
$PAGE->set_title($title);
$PAGE->set_heading($heading);


// Собрать данные для виджетов

$widgetfactory = new logstore_xapi\local\queue_monitor\widget_factory($DB);
$widgets = $widgetfactory->make_widget_collection();
$renderable = new logstore_xapi\output\renderable\page_queue_monitor($widgets);

/** @var logstore_xapi\output\renderer $renderer */
$renderer = $PAGE->get_renderer('logstore_xapi');

echo $renderer->header();
echo $renderer->render($renderable);
echo $renderer->footer();
