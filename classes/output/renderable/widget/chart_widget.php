<?php

namespace logstore_xapi\output\renderable\widget;

use coding_exception;
use core\chart_axis;
use renderer_base;
use templatable;
use tool_monitor\output\managerules\renderable;
use core\chart_base;
use core\chart_line;
use core\chart_bar;
use core\chart_pie;
use core\chart_series;
use logstore_xapi\local\queue_monitor\widget\chart_widget_interface;

class chart_widget implements renderable, templatable {

    /**
     * @var chart_widget_interface
     */
    protected $widget;

    /**
     *
     */
    public function __construct(chart_widget_interface $widget) {
        $this->widget = $widget;
    }

    /**
     * @inheritdoc
     */
    public function export_for_template(renderer_base $renderer) {
        $context = [
            'content' => $renderer->render($this->widget->get_chart_instance()),
            'classlist' => ''
        ];

        return $context;
    }
}
