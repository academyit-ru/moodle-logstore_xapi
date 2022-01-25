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

namespace src\transformer\utils\get_activity;
defined('MOODLE_INTERNAL') || die();

use logstore_xapi\local\persistent\xapi_attachment;


function attachments(array $config, $event) {

    define('USAGE_TYPE', 'http://id.tincanapi.com/attachment/supporting_media');

    $attachments = xapi_attachment::get_records(['eventid' => $event->id]);

    $result = [];
    foreach ($attachments as $attachment) {
        $result[] = [
            'fileUrl' => $attachment->s3_url,
            'display' => [
                'ru-RU' => $attachment->s3_filename
            ],
            'contentType' => $attachment->s3_contenttype,
            'length' => $attachment->s3_filesize,
            'sha2' => $attachment->s3_sha2,
            'usageType' => USAGE_TYPE
        ];
    }

    return $result;
}
