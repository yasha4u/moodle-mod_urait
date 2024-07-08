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
 * В этом файле описывается что должно быть выплолнено при обновлении плагина.
 *
 * @package     mod_urait
 * @copyright   2024 LMS-Service {@link https://lms-service.ru/}
 * @author      Valeriy Tyulin
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * В этой функции описывается что должно быть выполнено при обновлении плагина.
 *
 * @param $oldversion
 * @return bool
 * @throws coding_exception
 * @throws dml_exception
 */
function xmldb_urait_upgrade($oldversion) {

    return true;
}
