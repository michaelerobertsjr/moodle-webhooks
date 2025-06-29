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
 * This file contains the functions used by the plugin.
 *
 * @package   local_webhooks
 * @copyright 2017 "Valentin Popov" <info@valentineus.link>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined("MOODLE_INTERNAL") || die();

require_once(__DIR__ . "/locallib.php");

/**
 * Change the status of the service.
 *
 * @param  number  $serviceid
 * @return boolean
 */
function local_webhooks_change_status($serviceid) {
    global $DB;

    $result = false;
    if ($record = local_webhooks_get_record($serviceid)) {
        $record->enable = !boolval($record->enable);
        $result = local_webhooks_update_record($record);
    }

    return $result;
}

/**
 * Search for services that contain the specified event.
 *
 * @param  string  $eventname
 * @param  boolean $active
 * @return array
 */
function local_webhooks_search_services_by_event($eventname, $active = false) {
    $recordlist = local_webhooks_get_list_records();
    $active     = boolval($active);
    $result     = array();

    foreach ($recordlist as $record) {
        if (!empty($record->events[$eventname])) {
            if ($active && boolval($record->enable)) {
                $result[] = $record;
            }

            if (!$active) {
                $result[] = $record;
            }
        }
    }

    return $result;
}

/**
 * Get the record from the database.
 *
 * @param  number $serviceid
 * @return object
 */
function local_webhooks_get_record($serviceid) {
    global $DB;

    $servicerecord = $DB->get_record("local_webhooks_service", array("id" => $serviceid), "*", MUST_EXIST);

    if (!empty($servicerecord->events)) {
        $servicerecord->events = local_webhooks_deserialization_data($servicerecord->events);
    }

    return $servicerecord;
}

/**
 * Get all records from the database.
 *
 * @param  number $limitfrom
 * @param  number $limitnum
 * @return array
 */
function local_webhooks_get_list_records($limitfrom = 0, $limitnum = 0) {
    global $DB;

    $listrecords = $DB->get_records("local_webhooks_service", null, "id", "*", $limitfrom, $limitnum);

    foreach ($listrecords as $servicerecord) {
        if (!empty($servicerecord->events)) {
            $servicerecord->events = local_webhooks_deserialization_data($servicerecord->events);
        }
    }

    return $listrecords;
}

/**
 * Get a list of all system events.
 *
 * @return array
 */
function local_webhooks_get_list_events() {
    return report_eventlist_list_generator::get_all_events_list(true);
}

/**
 * Create an entry in the database.
 *
 * @param  object  $record
 * @return boolean
 */
function local_webhooks_create_record($record) {
    global $DB;

    if (!empty($record->events)) {
        $record->events = local_webhooks_serialization_data($record->events);
    }

    $result = $DB->insert_record("local_webhooks_service", $record, true, false);

    /* Clear the plugin cache */
    local_webhooks_cache_reset();

    /* Event notification */
    local_webhooks_events::service_added($result);

    return boolval($result);
}

/**
 * Update the record in the database.
 *
 * @param  object  $data
 * @return boolean
 */
function local_webhooks_update_record($record) {
    global $DB;

    if (empty($record->id)) {
        print_error("missingparam", "error", null, "id");
    }

    $record->events = !empty($record->events) ? local_webhooks_serialization_data($record->events) : null;
    $result = $DB->update_record("local_webhooks_service", $record, false);

    /* Clear the plugin cache */
    local_webhooks_cache_reset();

    /* Event notification */
    local_webhooks_events::service_updated($record->id);

    return boolval($result);
}

/**
 * Delete the record from the database.
 *
 * @param  number  $serviceid
 * @return boolean
 */
function local_webhooks_delete_record($serviceid) {
    global $DB;

    $result = $DB->delete_records("local_webhooks_service", array("id" => $serviceid));

    /* Clear the plugin cache */
    local_webhooks_cache_reset();

    /* Event notification */
    local_webhooks_events::service_deleted($serviceid);

    return boolval($result);
}

/**
 * Delete all records from the database.
 *
 * @return boolean
 */
function local_webhooks_delete_all_records() {
    global $DB;

    $result = $DB->delete_records("local_webhooks_service", null);

    /* Clear the plugin cache */
    local_webhooks_cache_reset();

    /* Event notification */
    local_webhooks_events::service_deletedall();

    return boolval($result);
}

/**
 * Create a backup.
 *
 * @return string
 */
function local_webhooks_create_backup() {
    $listrecords = local_webhooks_get_list_records();
    $result      = local_webhooks_serialization_data($listrecords);

    /* Event notification */
    local_webhooks_events::backup_performed();

    return $result;
}

/**
 * Restore from a backup.
 *
 * @param string $data
 */
function local_webhooks_restore_backup($data, $deleterecords = false) {
    $listrecords = local_webhooks_deserialization_data($data);

    if (boolval($deleterecords)) {
        local_webhooks_delete_all_records();
    }

    foreach ($listrecords as $servicerecord) {
        local_webhooks_create_record($servicerecord);
    }

    /* Event notification */
    local_webhooks_events::backup_restored();
}

/**
 * Send the event remotely to the service.
 *
 * @param  array  $event
 * @param  object $callback
 * @return array
 */
function local_webhooks_send_request($event, $callback) {
    global $CFG;

    $event["host"]  = parse_url($CFG->wwwroot)["host"];
    $event["token"] = $callback->token;
    $event["extra"] = $callback->other;

    $curl = new curl();
    $curl->setHeader(array("Content-Type: application/" . $callback->type));
    
    $requestbody = json_encode($event);
    $timesent = time();
    
    // Send the request
    $curl->post($callback->url, $requestbody);
    $response = $curl->getResponse();
    
    // Parse response for logging
    $responsecode = null;
    $responsebody = '';
    $success = 0;
    
    if ($response) {
        $responsebody = json_encode($response);
        
        // Extract HTTP status code
        if (isset($response['HTTP/1.1'])) {
            $statusline = $response['HTTP/1.1'];
            if (preg_match('/^(\d{3})/', $statusline, $matches)) {
                $responsecode = intval($matches[1]);
                $success = ($responsecode >= 200 && $responsecode < 300) ? 1 : 0;
            }
        } else if (is_array($response) && !empty($response)) {
            // If we got a response but no HTTP status, assume success
            $success = 1;
            $responsecode = 200;
        }
    }
    
    // Log the webhook request
    local_webhooks_log_request($callback->id, $event['eventname'] ?? 'unknown', $callback->url, 
                               $requestbody, $responsecode, $responsebody, $success, $timesent);

    /* Event notification */
    local_webhooks_events::response_answer($callback->id, $response);

    return $response;
}

/**
 * Log a webhook request to the database.
 *
 * @param  int    $serviceid
 * @param  string $eventname
 * @param  string $url
 * @param  string $requestbody
 * @param  int    $responsecode
 * @param  string $responsebody
 * @param  int    $success
 * @param  int    $timesent
 * @return boolean
 */
function local_webhooks_log_request($serviceid, $eventname, $url, $requestbody, $responsecode, $responsebody, $success, $timesent) {
    global $DB;

    $record = new stdClass();
    $record->serviceid = $serviceid;
    $record->eventname = $eventname;
    $record->url = $url;
    $record->requestbody = $requestbody;
    $record->responsecode = $responsecode;
    $record->responsebody = $responsebody;
    $record->success = $success;
    $record->timesent = $timesent;

    return $DB->insert_record('local_webhooks_log', $record);
}

/**
 * Get webhook logs with optional filtering.
 *
 * @param  int    $serviceid  Optional service ID filter
 * @param  int    $limit      Optional limit
 * @param  int    $offset     Optional offset
 * @return array
 */
function local_webhooks_get_logs($serviceid = null, $limit = 0, $offset = 0) {
    global $DB;

    $params = array();
    $where = '1=1';
    
    if ($serviceid) {
        $where .= ' AND serviceid = ?';
        $params[] = $serviceid;
    }
    
    $sql = "SELECT l.*, s.title as servicetitle 
            FROM {local_webhooks_log} l 
            LEFT JOIN {local_webhooks_service} s ON l.serviceid = s.id 
            WHERE $where 
            ORDER BY l.timesent DESC";
    
    if ($limit > 0) {
        return $DB->get_records_sql($sql, $params, $offset, $limit);
    } else {
        return $DB->get_records_sql($sql, $params);
    }
}

/**
 * Get a specific webhook log record.
 *
 * @param  int $logid
 * @return object|false
 */
function local_webhooks_get_log($logid) {
    global $DB;

    $sql = "SELECT l.*, s.title as servicetitle 
            FROM {local_webhooks_log} l 
            LEFT JOIN {local_webhooks_service} s ON l.serviceid = s.id 
            WHERE l.id = ?";
    
    return $DB->get_record_sql($sql, array($logid));
}