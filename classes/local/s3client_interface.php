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
 * Интерфейс S3 клиент для отправки артефактов обучения
 *
 * @package   logstore_xapi
 * @author    Victor Kilikaev vkilikaev@it.ru
 * @copyright academyit.ru
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\local;

defined('MOODLE_INTERNAL') || die();

interface s3client_interface {

    /**
     * @param string $key
     * @param mixed $body
     * @param null|string $bucketname
     * @param string $acl по умолчанию public-read
     *
     * @return \stdClass объект с полями etag, url, lastmodified
     */
    public function upload($key, $body, $bucketname = null,  $acl = 'public-read');
}