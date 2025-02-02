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
 * Базовый класс ищеек файлов вложений, связанных с событием журнала
 *
 * @package    logstore_xapi
 * @author     Victor Kilikaev vkilikaev@it.ru
 * @copyright  academyit.ru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace logstore_xapi\local;

use coding_exception;
use stored_file;

abstract class file_finder {

    /**
     * Создаст подходящий класс ищейку для события журнала
     * @param log_event $logevent
     *
     * @return file_finder
     */
    public static function factory(log_event $logevent) {
        global $DB;
        $fs = get_file_storage();

        switch ($logevent->component) {
            case 'mod_assign':
                return new file_finder_mod_assign($DB, $fs);
            case 'core':
            case 'mod_page':
            case 'mod_url':
            case 'mod_label':
            case 'mod_resource':
            case 'mod_chat':
            case 'mod_quiz':
                return new class extends file_finder {
                    public function find_for(log_event $logevent) {
                        return [];
                    }
                    public function has_attachments(log_event $logevent) {
                        return false;
                    }
                };
            default:
                throw new coding_exception(sprintf('%s: Неизвестный компонет %s', static::class, $logevent->component), json_encode($logevent->to_record()));
        }
    }

    /**
     * @param log_event $logevent
     * @return stored_file[]
     */
    abstract public function find_for(log_event $logevent);

    /**
     * @param log_event $logevent
     * @return bool
     */
    abstract public function has_attachments(log_event $logevent);
}
