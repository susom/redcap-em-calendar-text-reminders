<?php
namespace Stanford\CalendarTextReminders;

use \Plugin as Plugin;

/**
 * Class CalendarEventsGroup
 * Object to hold the event group defined by user
 * This corresponds to the subgroup in the config file and shares these settings:
 *   event-list
 *   text-message
 *   date-offset
 *
 *
 */
class CalendarEventsGroup {

    public $project_id;

    public $text_message;
    public $date_offset;
    public $event_list;
    public $adhoc;

    public $fixed_text_message;

    public $eventNamesToId;

    public function __construct($project_id, $event_list, $text_message, $date_offset) {
        $this->project_id = $project_id;

        $this->text_message = $text_message;
        $this->date_offset = $date_offset;

        //deal with event list
        //if null, then null
        if ($this->isNullOrEmpty($event_list)) {
            $this->event_list = null;
        } else {
            $event_array = explode(PHP_EOL, $event_list);
//            Plugin::log($event_array, "DEBUG", "EVENT ARRAY");

            //convert each of the event_names to event_id to use in the sql query
            foreach ($event_array as $event) {
                  //handle adhoc entry differently
                //set another parameter as adhoc to add null as one of the allowed events
                if (empty(trim($event) )) {
                    continue;
                }
                if (strtolower($event) == 'adhoc') {
                    $this->adhoc = true;
                } else {

                    $event_ids[] = $this->getEventId($event);
                }
            }

            $this->event_list = implode(',', $event_ids);
        }

    }

    /**
     * Convert [topline], [date] [time] with information found in tcalendar
     * @param $entry
     */
    function fixTextMessage($entry) {
        $status = true;

        $event_time = $entry['event_time'];
        $event_date = $entry['event_date'];

        //construct the text from the template
        $template = $this->text_message;

        //check for [date]
        $template = str_replace("[date]", $event_date, $template);
        $template = str_replace("[time]", $event_time, $template);

        if (preg_match('/[topline]/',$template)) {
            $notes = $entry['notes'];
            $first_line = strtok($notes, PHP_EOL);

            //confirm $first_line is prepended with "TEXT" (safeguard to prevent inadvertent sends
            $prefix_exists = preg_match_all('/TEXT:(.*)/', $first_line, $match, PREG_SET_ORDER, 0);

            if ($prefix_exists) {
                //Plugin::log($match,"DEBUG", "MATCHED");
                $template = str_replace("[topline]", $match[0][1], $template);
            } else {
                //there was no prefix.  log that SMS was not sent because no prefix was found
                $status = false;
                $template = "The message was not prepended with 'TEXT:': " . $first_line;
            }
        }

        return array($status, $template);
    }


    function checkCalendar() {
        $dt = new \DateTime();
        $date = $dt->format("Y-m-d");
        $time = $dt->format("H:i");

        $target_date = date('Y-m-d', strtotime($this->date_offset.' days',strtotime($date)));

        $sql = sprintf("select * 
                from redcap_events_calendar
                where
	            project_id = %d
                and event_date = '%s'",
            intval($this->project_id),
            db_escape($target_date)
        );

        if (!$this->isNullOrEmpty($this->event_list)) {
            $sql .= sprintf(" and (event_id in (%s)",
                db_escape($this->event_list)
            );

            if ($this->adhoc) {
                $sql .= " or event_id is null)";
            } else {
                $sql .= ")";
            }
        } else {
            if ($this->adhoc) {
                $sql .= " and event_id is null";
            }
        }

        $q = db_query($sql);

        while(($row = db_fetch_assoc($q)) != NULL) {
            $calsync_fields[$row['cal_id']] = $row;
        }

        return ($calsync_fields);

    }

    /**
     * Function for basic field validation (present and neither empty nor only white space
     * @param $field
     * @return bool
     */
    function isNullOrEmpty($field) {
        return (!isset($field) || trim($field)==='');
    }

    /**
     * Return the event_id from the event_name
     * @param $event_name
     * @return mixed
     * @throws exception
     */
    public function getEventId($event_name)
    {
        if (empty($this->eventNamesToId)) {
            // Set some event info
            $eventNames = \REDCap::getEventNames(TRUE, FALSE);
            $this->eventNamesToId = array_flip($eventNames);
        }

        if (!isset($this->eventNamesToId[$event_name])) {
            throw new exception("Invalid event name: $event_name");
        }
        return $this->eventNamesToId[$event_name];
    }
}