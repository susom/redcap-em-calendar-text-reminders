<?php
/**
 * Created by PhpStorm.
 * User: jael
 * Date: 10/26/17
 * Time: 8:20 PM
 */

use \Plugin as Plugin;
/** @var \Stanford\CalendarTextReminders\CalendarTextReminders $module */


Plugin::log('------- Starting Calendar SMS Cron -------', "INFO");
Plugin::log($project_id, 'PID');

$module->textCron($project_id);




