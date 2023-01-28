<?php
// This file is part of mod_organizer for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * view_action.php
 *
 * @package   mod_organizer
 * @author    Andreas Hruska (andreas.hruska@tuwien.ac.at)
 * @author    Katarzyna Potocka (katarzyna.potocka@tuwien.ac.at)
 * @author    Thomas Niedermaier (thomas.niedermaier@meduniwien.ac.at)
 * @author    Andreas Windbichler
 * @author    Ivan Šakić
 * @copyright 2014 Academic Moodle Cooperation {@link http://www.academic-moodle-cooperation.org}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once(dirname(__FILE__) . '/view_action_form_delete.php');
require_once(dirname(__FILE__) . '/view_lib.php');
require_once(dirname(__FILE__) . '/messaging.php');

$mode = optional_param('mode', null, PARAM_INT);
$action = optional_param('action', null, PARAM_ALPHANUMEXT);
$slot = optional_param('slot', null, PARAM_INT);
$slots = organizer_get_param_slots();

list($cm, $course, $organizer, $context, $redirecturl) = organizer_slotpages_header();

require_login($course, false, $cm);

$logurl = 'view_action.php?id=' . $cm->id . '&mode=' . $mode . '&action=' . $action;

require_capability('mod/organizer:deleteslots', $context);

if (!$slots) {
    $redirecturl->param('messages[]', 'message_warning_no_slots_selected');
    redirect($redirecturl);
}

$mform = new organizer_delete_slots_form(null, array('id' => $cm->id, 'mode' => $mode, 'slots' => $slots));

if ($data = $mform->get_data()) {
    if (isset($slots)) {
        $notified = 0;
        foreach ($slots as $slotid) {
            if ($organizer->isgrouporganizer == ORGANIZER_GROUPMODE_NEWGROUPSLOT ||
                    $organizer->isgrouporganizer == ORGANIZER_GROUPMODE_NEWGROUPBOOKING) {
                organizer_delete_coursegroup(null, $slotid);
            }
            $notified += organizer_delete_appointment_slot($slotid);
        }

        $slotsdeleted = count($slots);

        $slots = $DB->get_records('organizer_slots', array('organizerid' => $organizer->id));

        $event = \mod_organizer\event\slot_deleted::create(
            array(
                'objectid' => $PAGE->cm->id,
                'context' => $PAGE->context
            )
        );
        $event->trigger();


        if ($organizer->isgrouporganizer == ORGANIZER_GROUPMODE_EXISTINGGROUPS) {
            $redirecturl->param('messages[]', 'message_info_slots_deleted_group');
            $groups = groups_get_all_groups($course->id, 0, $cm->groupingid, 'id');
            $sql = 'SELECT COUNT(DISTINCT app.id) as total
                FROM {organizer_slots} org
                JOIN {organizer_slot_appointments} app ON org.id = app.slotid
                WHERE org.organizerid = :organizerid and app.groupid = :groupid';
            $params = array('organizerid' => $organizer->id);
            $appointmentstotal = 0;
            foreach ($groups as $group) {
               $params['groupid'] = $group->id;
               $appointmentstotal += $DB->count_records_sql($sql, $params);
            }
            $registrantstotal = count($groups);
            $placestotal = count($slots);
        } else {
            $redirecturl->param('messages[0]', 'message_info_slots_deleted');
            $slots = $DB->get_records('organizer_slots', array('organizerid' => $organizer->id));
            $placestotal = 0;
            foreach ($slots as $slot) {
                $placestotal += $slot->maxparticipants;
            }
            $entries = get_enrolled_users($context, 'mod/organizer:register');
            list($registrantstotal, $notreachedmin, ) =
                organizer_multiplebookings_statistics($organizer, null, $entries);
            $sql = 'SELECT COUNT(DISTINCT app.id) as total
                FROM {organizer_slots} org
                JOIN {organizer_slot_appointments} app ON org.id = app.slotid
                WHERE org.organizerid = :organizerid';
            $params = array('organizerid' => $organizer->id);
            $appointmentstotal = $DB->count_records_sql($sql, $params);
        }

        $freetotal = $placestotal - $appointmentstotal;

        $redirecturl->param('data[deleted]', $slotsdeleted);
        $redirecturl->param('data[notified]', $notified); // Amount notified participants.
        $redirecturl->param('data[freeslots]', $freetotal); // Free places.
        $redirecturl->param('data[notreachedmin]', $notreachedmin); // Amount outstanding bookings.
        if ($organizer->isgrouporganizer == ORGANIZER_GROUPMODE_EXISTINGGROUPS) {
            $redirecturl->param('messages[1]', 'message_info_available_group');
        } else {
            $redirecturl->param('messages[1]', 'message_info_available');
        }

    }
    redirect($redirecturl);
} else if ($mform->is_cancelled()) {
    redirect($redirecturl);
} else {
    organizer_display_form($mform, get_string('title_delete', 'organizer'));
}
print_error('If you see this, something went wrong with delete action!');

die;