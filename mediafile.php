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
 * This file plays the mediafile set in lesson settings.
 *
 *  If there is a way to use the resource class instead of this code, please change to do so
 *
 *
 * @package    mod
 * @subpackage lesson
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/languagelesson/locallib.php');

$id = required_param('id', PARAM_INT);    // Course Module ID.
$printclose = optional_param('printclose', 0, PARAM_INT);

$cm = get_coursemodule_from_id('languagelesson', $id, 0, false, MUST_EXIST);;
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$lesson = new languagelesson($DB->get_record('languagelesson', array('id' => $cm->instance), '*', MUST_EXIST));

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
$canmanage = has_capability('mod/languagelesson:manage', $context);

$url = new moodle_url('/mod/languagelesson/mediafile.php', array('id'=>$id));
if ($printclose !== '') {
    $url->param('printclose', $printclose);
}
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title($course->shortname);

$lessonoutput = $PAGE->get_renderer('mod_languagelesson');

// Get the mimetype.
$mimetype = mimeinfo("type", $lesson->mediafile);

if ($printclose) {  // This is for framesets.
    if ($lesson->mediaclose) {
        echo $lessonoutput->header($lesson, $cm);
        echo $OUTPUT->box('<form><div><input type="button" onclick="top.close();" value="'.get_string("closewindow").'" /></div></form>', 'lessonmediafilecontrol');
        echo $lessonoutput->footer();
    }
    exit();
}

echo $lessonoutput->header($lesson, $cm);

// TODO: this is copied from view.php - the access should be the same!
// Check these for students only TODO: Find a better method for doing this!
//     Check lesson availability.
//     Check for password.
//     Check dependencies.
//     Check for high scores.
if (!$canmanage) {
    if (!$lesson->is_accessible()) {  // Deadline restrictions.
        echo $lessonoutput->header($lesson, $cm);
        if ($lesson->deadline != 0 && time() > $lesson->deadline) {
            echo $lessonoutput->languagelesson_inaccessible(get_string('lessonclosed', 'languagelesson', userdate($lesson->deadline)));
        } else {
            echo $lessonoutput->languagelesson_inaccessible(get_string('lessonopen', 'languagelesson', userdate($lesson->available)));
        }
        echo $lessonoutput->footer();
        exit();
    } else if ($lesson->usepassword && empty($USER->lessonloggedin[$lesson->id])) { // Password protected lesson code.
        $correctpass = false;
        if (!empty($userpassword) && (($lesson->password == md5(trim($userpassword))) || ($lesson->password == trim($userpassword)))) {
            // With or without md5 for backward compatibility (MDL-11090).
            $USER->lessonloggedin[$lesson->id] = true;
            if ($lesson->highscores) {
                // Logged in - redirect so we go through all of these checks before starting the lesson.
                redirect("$CFG->wwwroot/mod/languagelesson/view.php?id=$cm->id");
            }
        } else {
            echo $lessonoutput->header($lesson, $cm);
            echo $lessonoutput->login_prompt($lesson, $userpassword !== '');
            echo $lessonoutput->footer();
            exit();
        }
    }
}

// Print the embedded media html code.
echo $OUTPUT->box(languagelesson_get_media_html($lesson, $context));

if ($lesson->mediaclose) {
    echo '<div class="lessonmediafilecontrol">';
    echo $OUTPUT->close_window_button();
    echo '</div>';
}

echo $lessonoutput->footer();