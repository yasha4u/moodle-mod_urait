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
 * @package mod_urait
 * @copyright 2024 LMS-Service {@link https://lms-service.ru/}
 * @author Daniil Romanov
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    if ($ADMIN->fulltree) {
        $cutomerkey = new \mod_urait\admin_setting_configtext_with_guid_validation(
            'mod_urait/consumerkey',
            get_string('consumerkey', 'urait'),
            '',
            '');
        $settings->add($cutomerkey);

        $token = new \mod_urait\admin_setting_configtext_with_guid_validation(
            'mod_urait/token',
            get_string('token', 'urait'),
            '',
        '');
        $settings->add($token);
    }
}

