<?php
namespace Stanford\CalendarTextReminders;

/** @var \Stanford\CalendarTextReminders\CalendarEventsGroup $event_group */

use \REDCap as REDCap;
use \Plugin as Plugin;
use \ExternalModules\ExternalModules;

include "textManager.php";
include 'SmsRecord.php';
include 'CalendarEventsGroup.php';

class CalendarTextReminders extends \ExternalModules\AbstractExternalModule {

    /**
     * Called by cron job to initiate the daily texts
     */
    public function startCron() {
        //get all projects that are enabled for this module
        $enabled = ExternalModules::getEnabledProjects($this->PREFIX);

        $url = $this->getUrl('calendar_sms_cron.php', false, true);

        while ($proj = db_fetch_assoc($enabled)) {
            $pid = $proj['project_id'];
            $this_url = $url . '&pid=' . $pid;
            Plugin::log(($this_url), "URL IS ");
            http_get($this_url);
        }

    }

    /**
     * @param $project_id
     */
    public static function textCron($project_id) {
        $settings = ExternalModules::getProjectSettingsAsArray("calendar_text_reminders", $project_id);


        //Fire up the Twilio helper class
        $tm = new textManager($settings['twilio-sid']['value'], $settings['twilio-token']['value'],
            $settings['twilio-number']['value'], false);

        //Set up the Calendar Events Groups
        foreach ($settings['event-list']['value'] as $k => $v) {
            $event_groups[$k] = new CalendarEventsGroup($project_id,
                $settings['event-list']['value'][$k],
                $settings['text-message']['value'][$k], $settings['date-offset']['value'][$k]);
        }


        //check for calendar entries that need to be sent out today
        //Deal with each event_group
        foreach ($event_groups as $key => $event_group) {
            //\Plugin::log($event_group, "DEBUG", "STARTING on EVENT GROUP: $key");
            $cal_entries = $event_group->checkCalendar();
            //\Plugin::log($cal_entries, "DEBUG", "CAL ENTRIES");

            if ($cal_entries) {

                //REDCap::getData for all the affected records
                $all_records = array_column($cal_entries, 'record');
                $unique_records = array_unique($all_records);

                //get records and iterate over to see what needs to be sent
                $get_fields = array(
                    $settings['sms-phone-field']['value'],
                    $settings['sms-inactive-field']['value'],
                    $settings['sms-status']['value'],
                    $settings['sms-timestamp']['value']
                );

                $records = REDCap::getData($project_id, 'array', $unique_records, $get_fields);
                //print "ALL DATA<pre>".print_r($records, true)."</pre>";

                //iterate over the cal_entries and send text message to each
                foreach ($cal_entries as $cal_id => $entry) {
                    //fix the text message for this entry
                    $send_record_id = $entry['record'];
                    //\Plugin::log("Processing record $send_record_id...", "DEBUG");
                    $record_data = $records[$send_record_id];
                    //Plugin::log($record_data, "DEBUG", "RECORD DATA FOR".$send_record_id);

                    //instantiate a smsRecord object
                    $sr = new SmsRecord($send_record_id, $record_data, $settings);
                    //\Plugin::log($sr, "DEBUG", "NEW SMS RECORD");

                    //Skip if inactive
                    if ($sr->inactive) {
                        Plugin::log($sr->inactive, "DEBUG", "INACTIVE: Do not send sms to record_id $send_record_id");
                        continue;
                    }

                    //Skip if no phone number
                    if (empty($sr->phone)) {
                        Plugin::log("Missing phone for record id " . $send_record_id);
                        continue;
                    }

                    //Skip if timestamped today
                    if (date('Ymd') == date('Ymd', strtotime($sr->timestamp))) {
                        Plugin::log($sr->timestamp, "DEBUG", "TEXT SENT TODAY ALREADY: Do not send sms to record_id $send_record_id");
                        continue;
                    }


                    list ($status, $fixed_text) = $event_group->fixTextMessage($entry);

                    //check status to see if text should still be sent
                    if ($status) {
                        //send the fixed text
                        //Plugin::log($fixed_text, "DEBUG", "SENDING FIXED TEXT MESSAGE to send to record $send_record_id at phone #:" . $sr->phone);

                        $result = $tm->sendSms($sr->phone, $fixed_text);
                        //$result = true;

                        if ($result === true) {
                            $sr->logSms(array($fixed_text), "SMS sent to " . $sr->phone);
                        } else {
                            $msg = "Error while attempting to send text to " . $sr->phone . $result;
                            Plugin::log($result, "ERROR", $msg);
                            $sr::logEvent($msg, $send_record_id, $sr->status_event);
                        }
                    } else {
                        //log error event / did not send text
                        $msg = "Text not sent to " . $sr->phone ." : ". $fixed_text;
                        Plugin::log($msg, "ERROR");
                        $sr->logSms(array($msg), "SMS not sent to " . $sr->phone);
                        $sr::logEvent($msg, $send_record_id, $sr->status_event);

                    }

                }

            } else {
                Plugin::log("NO Calendar entries for event group $key", "DEBUG");
            }
        }


    }

}

