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
 * Redirect incoming course links to the correct course, after checking user login
 *
 * @package   local_campusconnect
 * @copyright 2013 Davo Smith, Synergy Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_campusconnect\courselink;
use local_campusconnect\participantsettings;

require_once(dirname(__FILE__).'/../../config.php');
global $DB, $SESSION, $FULLME, $USER, $OUTPUT, $PAGE;

require_login();

if (empty($user)) {
    $user = $USER;
}

$courseid = required_param('courseid', PARAM_INT);
$targeturl = optional_param('targeturl', '', PARAM_TEXT);
$returnurl = optional_param('returnurl', '', PARAM_TEXT);

$data = [
    'courseid' => $courseid,
    'targeturl' => $targeturl,
    'returnurl' => $returnurl,
];

$title = get_string('confirmprivacy', 'local_campusconnect');

$PAGE->set_context(context_system::instance());
$PAGE->set_title($title);
$PAGE->set_url('/local/campusconnect/consent.php');
$PAGE->set_heading($title);
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_campusconnect/privacymodal', $data);
echo $OUTPUT->footer();
die;


$participant = new participantsettings($courselink->ecsid, $courselink->mid);
if (!isguestuser() && $participant->is_import_token_enabled()) {

    $userdata = $participant->map_export_data($user);
    $userparams = http_build_query($userdata, '', '&');

    // Add the auth token.
    if (strpos($url, '?') !== false) {
        $url .= '&';
    } else {
        $url .= '?';
    }
    $url .= $userparams;

    $hash = self::get_ecs_hash($url, $courselink, $userdata, $participant->is_legacy_export());
    $url .= '&ecs_hash='.$hash;
}

return $url;

