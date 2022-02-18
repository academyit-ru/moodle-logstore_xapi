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
 * renderable класс страницы /admin/tool/log/store/xapi/queues/monitor.php
 *
 * @package    logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\output\renderable;

use logstore_xapi\local\queue_monitor\measurements_aggregator;
use logstore_xapi\local\queue_monitor\widget\chart_widget_interface;
use logstore_xapi\local\queue_monitor\widget_settigns;
use logstore_xapi\output\renderable\widget\chart_widget as chart_widget_renderable;
use logstore_xapi\output\renderable\widget\data_point_widget as data_point_widget_renderable;
use renderable;
use templatable;
use renderer_base;

class page_queue_monitor implements renderable, templatable {

    /**
     * @var chart_widget_interface[]
     */
    protected $widgets = [];

    /**
     * @param templatable[] $widgets
     */
    public function __construct(array $widgets) {
    }

    /**
     * @inheritdoc
     */
    public function export_for_template(renderer_base $renderer) {
        $renderablewidgets = $this->make_renderable_widgets();
        $context = [
            'widgets' => []
        ];
        foreach ($renderablewidgets as $widget) {
            $context['widgets'][] = $widget->export_for_template($renderer);
        }
        return $context;
    }

    /**
     * @return mixed[] <int, data_point_widget_interface|chart_widget_interface>
     *
     */
    protected function make_renderable_widgets() {
        $result = [];

        foreach ($this->widgets as $widget) {

            if (true === in_array(chart_widget_interface::class, class_implements($widget))) {
                $result[] = new chart_widget_renderable($widget);
            } else {
                $result[] = new data_point_widget_renderable($widget);
            }
        }
        return $result;
    }
}