<?php
// This file is part of the CampusConnect plugin for Moodle - http://moodle.org/
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
 * Handles the importing of membership lists from the ECS
 *
 * @package   local_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_campusconnect;

/**
 * Class to handle the importing of membership lists from the ECS
 *
 * @package   local_campusconnect
 * @copyright 2012 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Handle the before_course_viewed hook.
     *
     * @param \core_course\hook\before_course_viewed $hook The hook object containing course data.
     */
    public static function handle_before_course_viewed(\core_course\hook\before_course_viewed $hook): void {
        global $CFG;
        // Logic to check if redirection is needed.
        if ($externurl = self::extern_server_course($hook->course)) {
            redirect($externurl);
        }
    }

    /**
     * Logic to determine if a redirection is necessary.
     *
     * @param \stdClass $course The course being viewed.
     * @return bool True if redirection should occur, false otherwise.
     */
    private static function extern_server_course($course): bool {
        if ($url = courselink::check_redirect($course->id)) {
            return $url;
        }
        return course::check_redirect($course->id);
    }
}
