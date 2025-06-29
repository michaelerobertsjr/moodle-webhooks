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
 * Settings of the plugin.
 *
 * @package   local_webhooks
 * @copyright 2017 "Valentin Popov" <info@valentineus.link>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

if ($hassiteconfig) {
    // Create a category for webhooks
    $webhookscategory = new admin_category('local_webhooks_category', 
        new lang_string('pluginname', 'local_webhooks'));
    $ADMIN->add('server', $webhookscategory);
    
    // Add webhooks management page
    $ADMIN->add('local_webhooks_category', new admin_externalpage('local_webhooks',
        new lang_string('pluginname', 'local_webhooks'),
        new moodle_url('/local/webhooks/index.php')
    ));
    
    // Add webhooks logs page
    $ADMIN->add('local_webhooks_category', new admin_externalpage('local_webhooks_logs',
        new lang_string('webhooklogs', 'local_webhooks'),
        new moodle_url('/local/webhooks/logs.php')
    ));
}