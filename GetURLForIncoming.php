<?php

use \Plugin as Plugin;
/** @var \Stanford\CalendarTextReminders\CalendarTextReminders $module */

Plugin::log("------- GET URL for Calendar Text Reminders -------");
Plugin::log($project_id, "DEBUG","PID");



$url = $module->getUrl('incoming.php', false, true);
Plugin::log($url, "DEBUG", "URL");

echo "<b>------- GET URL to set in Twilio for incoming texts -------</b><br><br>";

echo "This is your URL for the webhook: <br>".$url;
echo "<br><br>Enter this url in your Twilio account settings:  ";
echo "Manage Numbers | Messaging | A message comes in | Webhook";