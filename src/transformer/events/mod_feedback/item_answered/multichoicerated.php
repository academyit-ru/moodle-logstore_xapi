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

namespace src\transformer\events\mod_feedback\item_answered;

defined('MOODLE_INTERNAL') || die();

use logstore_xapi\local\log_event;
use src\transformer\utils as utils;

function multichoicerated(array $config, log_event $event, \stdClass $feedbackvalue, \stdClass $feedbackitem) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $feedback = $repo->read_record_by_id('feedback', $feedbackitem->feedback);
    $lang = utils\get_course_lang($course);
    $presentedchoices = explode("|", substr($feedbackitem->presentation, 6));
    $choices = array_map(function ($presentation, $id) {
        $split = explode('####', $presentation);
        $rating = $split[0];
        $name = $split[1];
        return (object) [
            'rating' => intval($rating),
            'name' => $name,
            'id' => $id,
        ];
    }, $presentedchoices, array_keys($presentedchoices));
    $selectedchoice = $choices[intval($feedbackvalue->value) - 1];

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'http://adlnet.gov/expapi/verbs/answered',
            'display' => [
                $lang => 'answered'
            ],
        ],
        'object' => [
            'id' => $config['app_url'].'/mod/feedback/edit_item.php?id='.$feedbackitem->id,
            'definition' => [
                'type' => 'http://adlnet.gov/expapi/activities/cmi.interaction',
                'name' => [
                    $lang => $feedbackitem->name,
                ],
                'interactionType' => 'choice',
            ]
        ],
        'timestamp' => utils\get_event_timestamp($event),
        'result' => [
            'response' => $selectedchoice->name ? $selectedchoice->name : '',
            'completion' => $feedbackvalue->value !== '',
            'extensions' => [
                'http://learninglocker.net/xapi/moodle/feedback_item_rating' => $selectedchoice->rating,
                'http://learninglocker.net/xapi/cmi/choice/response' => $selectedchoice->name,
            ],
        ],
        'context' => [
            'platform' => $config['source_name'],
            'language' => $lang,
            'extensions' => [
                utils\INFO_EXTENSION => utils\get_info($config, $event),
            ],
            'contextActivities' => [
                'grouping' => [
                    utils\get_activity\site($config),
                    utils\get_activity\course($config, $course),
                    utils\get_activity\course_feedback($config, $course, $event->contextinstanceid),
                ],
                'category' => [
                    utils\get_activity\source($config),
                ]
            ],
        ]
    ]];
}