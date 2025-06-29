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
 * WebHook Logs Viewer.
 *
 * @package   local_webhooks
 * @copyright 2024
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . "/../../config.php");
require_once(__DIR__ . "/lib.php");
require_once($CFG->libdir . "/adminlib.php");
require_once($CFG->libdir . "/tablelib.php");

/* Optional parameters */
$logid = optional_param('logid', 0, PARAM_INT);
$serviceid = optional_param('serviceid', 0, PARAM_INT);

/* Link generation */
$baseurl = new moodle_url('/local/webhooks/logs.php');

/* Configure the context of the page */
admin_externalpage_setup('local_webhooks_logs', '', null, $baseurl, array());
$context = context_system::instance();

if (!empty($logid)) {
    // Show individual log details
    $log = local_webhooks_get_log($logid);
    if (!$log) {
        print_error('lognotfound', 'local_webhooks');
    }
    
    $titlepage = get_string('webhooklogdetails', 'local_webhooks');
    $PAGE->set_heading($titlepage);
    $PAGE->set_title($titlepage);
    echo $OUTPUT->header();
    
    // Back to logs link
    $backurl = new moodle_url('/local/webhooks/logs.php');
    echo html_writer::link($backurl, get_string('backtowebhooklogs', 'local_webhooks'), array('class' => 'btn btn-secondary mb-3'));
    
    // Display log details
    echo $OUTPUT->heading(get_string('webhooklogdetails', 'local_webhooks'));
    
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    
    $table->data[] = array(get_string('service', 'local_webhooks'), $log->servicetitle ?? get_string('unknown', 'moodle'));
    $table->data[] = array(get_string('eventname', 'local_webhooks'), $log->eventname);
    $table->data[] = array(get_string('url', 'moodle'), $log->url);
    $table->data[] = array(get_string('timesent', 'local_webhooks'), userdate($log->timesent));
    $table->data[] = array(get_string('success', 'moodle'), 
        $log->success ? get_string('yes', 'moodle') : get_string('no', 'moodle'));
    
    if ($log->responsecode) {
        $table->data[] = array(get_string('responsecode', 'local_webhooks'), $log->responsecode);
    }
    
    echo html_writer::table($table);
    
    // Request Body
    if (!empty($log->requestbody)) {
        echo $OUTPUT->heading(get_string('requestbody', 'local_webhooks'), 3);
        echo html_writer::tag('pre', htmlspecialchars($log->requestbody), array('class' => 'pre-scrollable'));
    }
    
    // Response Body
    if (!empty($log->responsebody)) {
        echo $OUTPUT->heading(get_string('responsebody', 'local_webhooks'), 3);
        echo html_writer::tag('pre', htmlspecialchars($log->responsebody), array('class' => 'pre-scrollable'));
    }
    
} else {
    // Show logs table
    $titlepage = get_string('webhooklogs', 'local_webhooks');
    $PAGE->set_heading($titlepage);
    $PAGE->set_title($titlepage);
    echo $OUTPUT->header();
    
    // Back to webhooks management
    $backurl = new moodle_url('/local/webhooks/index.php');
    echo html_writer::link($backurl, get_string('backtowebhooks', 'local_webhooks'), array('class' => 'btn btn-secondary mb-3'));
    
    echo $OUTPUT->heading(get_string('webhooklogs', 'local_webhooks'));
    
    // Create the logs table
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = array(
        get_string('timesent', 'local_webhooks'),
        get_string('service', 'local_webhooks'),
        get_string('eventname', 'local_webhooks'),
        get_string('url', 'moodle'),
        get_string('responsecode', 'local_webhooks'),
        get_string('success', 'moodle'),
        get_string('actions', 'moodle')
    );
    
    $logs = local_webhooks_get_logs($serviceid, 50); // Limit to 50 recent logs
    
    if (empty($logs)) {
        echo $OUTPUT->notification(get_string('nologs', 'local_webhooks'), 'info');
    } else {
        // Show info about logs displayed
        $totalcount = count($logs);
        if ($totalcount >= 50) {
            echo $OUTPUT->notification(get_string('showingrecent50', 'local_webhooks'), 'info');
        }
        
        foreach ($logs as $log) {
            $detailsurl = new moodle_url('/local/webhooks/logs.php', array('logid' => $log->id));
            $detailslink = html_writer::link($detailsurl, get_string('details', 'moodle'));
            
            $successtext = $log->success ? 
                html_writer::span(get_string('yes', 'moodle'), 'badge badge-success') :
                html_writer::span(get_string('no', 'moodle'), 'badge badge-danger');
            
            $table->data[] = array(
                userdate($log->timesent, get_string('strftimedatetimeshort', 'langconfig')),
                $log->servicetitle ?? get_string('unknown', 'moodle'),
                $log->eventname,
                format_string($log->url),
                $log->responsecode ?? '-',
                $successtext,
                $detailslink
            );
        }
        
        echo html_writer::table($table);
    }
}

/* Footer */
echo $OUTPUT->footer();