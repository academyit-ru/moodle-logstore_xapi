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
 * renderable класс виджета для отображения информации в виде числа.
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\output\renderable\widget;

use logstore_xapi\local\queue_monitor\widget\data_point_widget_interface;
use renderable;
use templatable;
use renderer_base;

class data_point_widget implements renderable, templatable {

    /**
     * @var data_point_widget_interface
     */
    protected $widget;

    /**
     *
     */
    public function __construct(data_point_widget_interface $widget) {
        $this->widget = $widget;
        $this->templatename = 'logstore_xapi/data_point_widget';
    }

    /**
     * @inheritdoc
     */
    public function export_for_template(renderer_base $renderer) {

        $content = $renderer->render_from_template(
            $this->templatename,
            [
                'datapoint' => $this->widget->get_datapoint(),
                'label' => $this->widget->get_label(),
            ]
        );

        $context = [
            'content' => $content,
            'classlist' => '',
        ];

        return $context;
    }
}
