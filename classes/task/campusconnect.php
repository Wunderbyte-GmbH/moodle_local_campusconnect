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
 * Scheduled task that removes relicts and unnecessary artifacts from the DB.
 *
 * @package local_campusconnect
 * @copyright 2023 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_campusconnect\task;

use local_campusconnect\connect;
use local_campusconnect\connect_exception;
use local_campusconnect\course_url;
use local_campusconnect\directorytree;
use local_campusconnect\ecssettings;
use local_campusconnect\enrolment;
use local_campusconnect\export;
use local_campusconnect\notification;
use local_campusconnect\participantsettings;
use local_campusconnect\receivequeue;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/local/campusconnect/lib.php');

/**
 * Class to handle scheduled task that removes relicts and unnecessary artifacts from the DB.
 *
 * @package local_campusconnect
 * @copyright 2024 Wunderbyte GmbH <info@wunderbyte.at>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class campusconnect extends \core\task\scheduled_task {

    /**
     * Get name of module.
     * @return string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('campusconnecttask', 'local_campusconnect');
    }

    /**
     * Scheduled task syncs with ECS server.
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function execute() {

        // Get updates from all ECS.
        $ecslist = ecssettings::list_ecs();
        foreach ($ecslist as $ecsid => $name) {
            $ecssettings = new ecssettings($ecsid);

            if ($ecssettings->time_for_cron()) {
                mtrace("Checking for updates on ECS server '".$ecssettings->get_name()."'");
                $connect = new connect($ecssettings);
                $queue = new receivequeue();

                try {
                    $queue->update_from_ecs($connect);
                    $queue->process_queue($ecssettings);
                } catch (connect_exception $e) {
                    local_campusconnect_ecs_error_notification($ecssettings, $e->getMessage());
                }

                mtrace("Sending updates to ECS server '".$ecssettings->get_name()."'");
                try {
                    export::update_ecs($connect);
                    course_url::update_ecs($connect);
                    enrolment::update_ecs($connect);
                } catch (connect_exception $e) {
                    local_campusconnect_ecs_error_notification($ecssettings, $e->getMessage());
                }

                $cms = participantsettings::get_cms_participant();
                if ($cms && $cms->get_ecs_id() == $ecssettings->get_id()) {
                    // If we are updating from the ECS with the CMS attached, then check the directory mappings (and sort order).
                    directorytree::check_all_mappings();
                }

                mtrace("Emailing any necessary notifications for '".$ecssettings->get_name()."'");
                notification::send_notifications($ecssettings);

                $ecssettings->update_last_cron();
            }
        }

    }
}
