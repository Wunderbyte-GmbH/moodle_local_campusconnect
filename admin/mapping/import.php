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
 * Settings page for campus connect
 *
 * @package    local_campusconnect
 * @copyright  2012 Synergy Learning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_campusconnect\ecssettings;
use local_campusconnect\form\campusconnect_import_form;
use local_campusconnect\metadata;

defined('MOODLE_INTERNAL') || die();

global $CFG, $PAGE, $OUTPUT;
require_once("$CFG->libdir/formslib.php");

$mform = new campusconnect_import_form();

$redir = new moodle_url('/local/campusconnect/admin/datamapping.php', ['type' => 'import']);

$errors = [];
$ecslist = ecssettings::list_ecs();
if ($mform->is_cancelled()) {

    redirect($redir);

} else if ($post = $mform->get_data()) {

    $coursedata = [];
    $courselinkdata = [];
    foreach ($ecslist as $ecsid => $ecsname) {
        $courselinkdata[$ecsid] = [];
        $coursedata[$ecsid] = [];
        foreach (metadata::list_local_fields() as $fieldname) {
            $fullfieldname = $ecsid.'_'.$fieldname.'_courselink';
            if (isset($post->{$fullfieldname})) {
                if ($fieldname == 'summary') {
                    $courselinkdata[$ecsid][$fieldname] = $post->{$fullfieldname}['text'];
                } else {
                    $courselinkdata[$ecsid][$fieldname] = $post->{$fullfieldname};
                }
            }
        }
        foreach (metadata::list_local_fields() as $fieldname) {
            $fullfieldname = $ecsid.'_'.$fieldname.'_course';
            if (isset($post->{$fullfieldname})) {
                if ($fieldname == 'summary') {
                    $coursedata[$ecsid][$fieldname] = $post->{$fullfieldname}['text'];
                } else {
                    $coursedata[$ecsid][$fieldname] = $post->{$fullfieldname};
                }
            }
        }
    }

    foreach ($ecslist as $ecsid => $ecsname) {
        if (isset($coursedata[$ecsid]) || isset($courselinkdata[$ecsid])) {
            $ecssettings = new ecssettings($ecsid);
            if (isset($coursedata[$ecsid])) {
                $metadata = new metadata($ecssettings, false);
                if (!$metadata->set_import_mappings($coursedata[$ecsid])) {
                    list ($errmsg, $errfield) = $metadata->get_last_error();
                    $errors[$ecsid.'_'.$errfield.'_course'] = $errmsg;
                }
            }
            if (isset($courselinkdata[$ecsid])) {
                $metadata = new metadata($ecssettings, true);
                if (!$metadata->set_import_mappings($courselinkdata[$ecsid])) {
                    list ($errmsg, $errfield) = $metadata->get_last_error();
                    $errors[$ecsid.'_'.$errfield.'_courselink'] = $errmsg;
                }
            }

        }
    }

    if (empty($errors)) {
        redirect($redir);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_campusconnect'));

print '<div class="controls"><strong><a href="?type=import">'.get_string('import', 'local_campusconnect').'</a></strong> |
            <a href="?type=export">'.get_string('export', 'local_campusconnect').'</a></div>';

$remotefields = metadata::list_remote_fields(false);
$helpcontent = '';
foreach ($remotefields as $remotefield) {
    $helpcontent .= '{'.$remotefield.'}<br />';
}
print "<div style='float: left; width: 45%; border: 1px solid #000; background: #ddd; margin: 10px 5px; padding: 5px;'><strong>"
    .get_string('courseavailablefields', 'local_campusconnect').':</strong><br />'.$helpcontent."</div>";

$remotefields = metadata::list_remote_fields(true);
$helpcontent = '';
foreach ($remotefields as $remotefield) {
    $helpcontent .= '{'.$remotefield.'}<br />';
}
print "<div style='float: right; width: 45%; border: 1px solid #000; background: #ddd; margin: 10px 5px; padding: 5px'><strong>"
    .get_string('courseextavailablefields', 'local_campusconnect').':</strong><br />'.$helpcontent."</div>";

echo html_writer::empty_tag('br', ['class' => 'clearer']);

if (!empty($errors)) {
    $mform->set_errors($errors);
}

$mform->display();
