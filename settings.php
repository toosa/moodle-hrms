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
 * Settings for local_hris
 *
 * @package    local_hris
 * @copyright  2025 Prihantoosa <pht854@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_hris', get_string('pluginname', 'local_hris'));
    $ADMIN->add('localplugins', $settings);

    // Enable/Disable HRIS API
    $settings->add(new admin_setting_configcheckbox(
        'local_hris/api_enabled',
        get_string('hris_api_enabled', 'local_hris'),
        get_string('hris_api_enabled_desc', 'local_hris'),
        1
    ));

    // API Key setting
    $settings->add(new admin_setting_configpasswordunmask(
        'local_hris/api_key',
        get_string('hris_api_key', 'local_hris'),
        get_string('hris_api_key_desc', 'local_hris'),
        ''
    ));
}