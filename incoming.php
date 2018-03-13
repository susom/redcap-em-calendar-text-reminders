<?php
namespace Stanford\CalendarTextReminders;

use \REDCap as REDCap;
use \Plugin as Plugin;

/**
 * Created by PhpStorm.
 * User: jael
 * Date: 11/6/17
 * Time: 8:55 AM
 */

/**
 * This is what is received from the Twilio text back:
 *
[REQUEST] (array): Array
(
[type] => module
[id] => 10
[page] => incoming
[pid] => 11983
[ToCountry] => US
[ToState] => CA
[SmsMessageSid] => SM...
[NumMedia] => 0
[ToCity] => INVERNESS
[FromZip] => 94028
[SmsSid] => SM...
[FromState] => CA
[SmsStatus] => received
[FromCity] => WOODSIDE
[Body] => Test case
[FromCountry] => US
[To] => +1415...
[ToZip] => 94937
[NumSegments] => 1
[MessageSid] => SM...
[AccountSid] => AC...
[From] => +1650...
[ApiVersion] => 2010-04-01
)

[text-back] => Array
(
  [value] => Array
  (
    [0] => true
    [1] => true
  )
)
[reply-request] => Array
(
  [value] => Array
  (
    [0] => C
    [1] => R
  )
)
[return-message] => Array
(
   [value] => Array
   )
    [0] => Thank you! Your appointment has been confirmed.
    [1] => Please contact 650-... to reschedule.
  )
)
*/


include_once 'textManager.php';
//require_once "../CalendarTextReminders.php";
//require_once "../../../external_modules/classes/ExternalModules.php";
//require_once "../../../external_modules/classes/AbstractExternalModules.php";

global $project_id;

\Plugin::log('--- PORTAL: Incoming Calendar Text Reminder SMS ---', 'DEBUG');


// Get the phone number to search REDCap
$from = $_POST['From'];
$pid = $_REQUEST['pid'];
$body = isset($_POST['Body']) ? $_POST['Body'] : '';

//xxyjl: for testing
//$from = '+16505295666';
//$body = 'C';
//xxyjl: end

$from_10 = substr($from, -10);
//\Plugin::log($_REQUEST, "DEBUG", "REQUEST");

$settings = \ExternalModules\ExternalModules::getProjectSettingsAsArray("calendar_text_reminders", $pid);
//\Plugin::log($settings, "SETTINGS: ");

//get the canned responses from the ProjectSettings
$settings_reply_request = $settings['reply-request']['value'];
$settings_return_message = $settings['return-message']['value'];

//\Plugin::log("Received text from phone " . $from_10 . " with this entry: " . $body);

//RESET VARS
$reply_txt = "";
$msg = array();
$data = array();

$msg[] = "---- " . date("Y-m-d H:i:s") . " ----";

switch ($body) {
    case null:
        $msg[] = "Received a null text from ". $from_10;
//        echo '$body is NULL';
        break;
    case '':
        //echo '$body has no value or is emtpy';
        $msg[] = "Received an empty text from ". $from_10;
        break;
    case 'STOP':
        //Default behavior when stop is received
        //echo "STOPPING";
        $msg[] = "STOP text was received. 'Stop sending SMS...' has been checked.";
        $reply_txt = "We have received a STOP text from phone number: " . $from_10 . ".\n" .
            "We have set the record as inactive and will no longer send texts to this number.";
        //check if inactive checkbox exists first
        $data = array_merge($data, array($settings['sms-inactive-field']['value'] . "___1"=>1));
        break;
    case (($key = array_search(strtoupper($body), $settings_reply_request)) !== false):
        //project defined returns (using the config settings)
        $reply_txt = $settings_return_message[$key];
        $msg[] = "Received this text: ". $body. " Texting back: " . $reply_txt;
        break;
    default:
        //no action defined.  Just log and do nothing.
        $msg[] = "Received this text: ". $body. ".  No action taken.";
        break;

}

if ($reply_txt != "") {
    //Fire up the Twilio helper class
    $tm = new textManager($settings['twilio-sid']['value'], $settings['twilio-token']['value'],
        $settings['twilio-number']['value'], false);

    $error = $tm->sendSms($from_10, $reply_txt);
    $msg[] = "Status of text: " .$error;
}

logSms($project_id, $from_10, $settings, $msg, $data);


/**
 * Log the text to the sms_log
 * logSms($project_id, $from_10, $settings, $msg, $data);
 *
 * @param $rec_id
 */
//function logSms($rec_id, $event_name, $text_msg)
function logSms($project_id, $from_10, $settings, $msg, $data) {
    $event_name = REDCap::getEventNames(true, false, $settings['sms-status-event']['value']);
//    \Plugin::log($event_name, "EVENT NAME for  " .  $settings['sms-status-event']['value']);

    $rec_id = SmsRecord::findRecordByPhone($project_id, textManager::formatToREDCapNumber($from_10),
        $settings['sms-phone-field']['value'],$event_name);

    $save_data = array_merge(
        array(REDCap::getRecordIdField() => $rec_id,
            $settings['sms-status']['value'] => implode("\n", $msg)),
        $data);

    if (!empty($event_name)) {
        $save_data['redcap_event_name'] = $event_name;
    }

    //\Plugin::log($save_data, "SAVING THIS!");
    SmsRecord::saveData($project_id, $save_data);

}

