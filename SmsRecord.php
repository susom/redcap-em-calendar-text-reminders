<?php
namespace Stanford\CalendarTextReminders;

use \REDCap as REDCap;
use \Plugin as Plugin;
// PROJECT CONSTANTS

/**
 * Class SmsRecord for text
 * Assumes we are running in REDCap Connect Context
 *
 */
class SmsRecord
{

    public $record_id;
    private $record;
    private $settings;

    public $eventNamesToId;

    public $errors = array();

    public $phone;
    public $inactive;        // true/false for active for SMS
    public $status;
    public $status_event;
    public $timestamp;
    public $status_append;
    public $date_offset;

    //FIELDNAMES in REDCap
    public $phone_name;
    public $inactive_name;        // true/false for active for SMS
    public $status_name;
    public $timestamp_name;



    // Create the object and load the data
    public function __construct($record_id, $record, $settings)
    {
        $this->record_id = $record_id;
        $this->record = $record;
        $this->settings = $settings;

        //CONVENIENCE FIELDS - remembering the data structure is too much for me
        //save the field names
        $this->phone_name = $settings['sms-phone-field']['value'];
        $this->inactive_name = $settings['sms-inactive-field']['value'];        // true/false for active for SMS
        $this->status_name = $settings['sms-status']['value'];
        $this->timestamp_name = $settings['sms-timestamp']['value'];


        //save the field values
        self::loadRecord($record);
    }

    // Load the record - we can prune this down to the minimum required data to save memory later
    private function loadRecord($record)
    {

        $this_event_id = key($record);
        $this_event_data = $record[$this_event_id];

        // Set active
        $this->inactive = current($this_event_data[$this->inactive_name]);

        // Set phone
        $this->phone = $this_event_data[$this->phone_name];

        // Set log
        $this->status = $this_event_data[$this->status_name];

        //Set log event
        $this->status_event = $this->settings['sms-status-event']['value'];

        $this->status_append = $this->settings['sms-status-append']['value'];

        // Set timestamp
        $this->timestamp = $this_event_data[$this->timestamp_name];

    }

    public static function findRecordByPhone($pid, $phone, $sms_number_field, $sms_event)
    {
        $get_fields = array(
            REDCap::getRecordIdField(),
            $sms_number_field
        );

        $filter = '';
        if ($sms_event != '') {
            $filter .= "[" . $sms_event . "]";
        }
        $filter .= "[" . $sms_number_field . "] = '$phone'";
            
        $records = REDCap::getData($pid, 'array', null, $get_fields, null, null, false, false, false, $filter);

        // return record_id or false
        reset($records);
        $first_key = key($records);
        return ($first_key);
    }


    /**
     * Return the event_id from the event_name (cache for multiple runs)
     * @param $event_name
     * @return mixed
     * @throws exception
     */
    public function getEventId($event_name)
    {
        if (empty($this->eventNamesToId)) {
            // Set some event info
            $eventNames = REDCap::getEventNames(TRUE, FALSE);
            $this->eventNamesToId = array_flip($eventNames);
        }

        if (!isset($this->eventNamesToId[$event_name])) {
            throw new exception("Invalid event name: $event_name");
        }
        return $this->eventNamesToId[$event_name];
    }

    /**
     * Log the message to the sms_log variable
     * @param $detail
     * @param string $header
     */
    public function logSms($detail, $header = '') {
        global $project_id;
        $data = array();

        $msg = array();
        $msg[] = "---- " . date("Y-m-d H:i:s") . " ----";
        $msg[] = " $header";
        foreach ($detail as $k => $v) $msg[] = "  $k: $v";

        if (!empty($data[$this->status_name])) $msg[] = "\n" . $data[$this->status_name];


        $status_event_name = REDCap::getEventNames(true, true, $this->status_event);

        $data = array(
            REDCap::getRecordIdField() => $this->record_id,
            //"record_id" => $this->record_id,
            'redcap_event_name' => $status_event_name,
            $this->status_name  => implode("\n", $msg),
            $this->timestamp_name => date("Y-m-d H:i:s")
        );

         self::saveData($project_id, $data);
    }

    public static function saveData($pid, $data)
    {
        //print "<br><br>SAVING DATA " . $pid;

        $response = REDCap::saveData($pid, 'json', json_encode(array($data)));
        //print "<pre>SAVING THIS result" . print_r(json_encode(array($data)), true) . "</pre>";
        //Plugin::log($response, "DEBUG", "Save Response for count");
        //print "<pre>saveData result" . print_r($response, true) . "</pre>";

        if (!empty($response['errors'])) {
            $msg = "Error creating record - ask administrator to review logs: " . json_encode($response);
            Plugin::log($msg);
            return ($response);
        }

        return null;

    }

    public static function logEvent($msg, $record_id, $event_name) {
        \REDCap::logEvent("Calendar Text Reminders Module", $msg, NULL, $record_id, $event_name);
    }

}
	



