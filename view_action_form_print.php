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
 * The main organizer configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package   mod_organizer
 * @copyright 2010 Ivan Šakić
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/formslib.php");
require_once(dirname(__FILE__) . '/custom_table_renderer.php');
require_once(dirname(__FILE__) . '/print.php');

class organizer_print_slots_form extends moodleform {

    private $_selcols;

    protected function definition() {
        $this->_add_slot_info();
        $this->_add_column_select();
    }

    private function _add_slot_info() {
        $mform = &$this->_form;
        $data = &$this->_customdata;

        $mform->addElement('hidden', 'id', $data['id']);
        $mform->addElement('hidden', 'mode', $data['mode']);

        // TODO: might cause crashes!
        $mform->addElement('hidden', 'action', 'print');
        $mform->setType('action', PARAM_ACTION);

        if (isset($data['slots'])) {
            foreach ($data['slots'] as $key => $slotid) {
                $mform->addElement('hidden', "slots[$key]", $slotid);
            }
        } else {
            print_error('This should not happen!');
        }
    }

    private function _add_column_select() {
        global $DB, $CFG;

        $mform = $this->_form;
        $data = &$this->_customdata;

        $params = array('slotid' => reset($data['slots']));
        $isgrouporganizer = $DB->get_field_sql(
                "SELECT o.isgrouporganizer
                FROM {organizer} o
                INNER JOIN {organizer_slots} s ON o.id = s.organizerid
                WHERE s.id = :slotid", $params);

        $identityfields = explode(',', $CFG->showuseridentity);
        if (array_search('idnumber', $identityfields) !== false) {
            $selcols = array('datetime', 'location', 'teacher', 'participant', 'idnumber', 'attended', 'grade', 'feedback');
        } else {
            $selcols = array('datetime', 'location', 'teacher', 'participant', 'attended', 'grade', 'feedback');
        }
        $this->_selcols = $selcols;
        
        if ($isgrouporganizer) {
            array_splice($selcols, 3, 0, 'groupname');
        }

        $mform->addElement('header', null, get_string('printoptions', 'organizer'));

        $mform->addElement('text', 'entriesperpage', get_string('numentries', 'organizer'), array('size' => '2'));
        $mform->setType('entriesperpage', PARAM_INT);
        $mform->setDefault('entriesperpage', '20');

        $textsize = $mform->addElement('select', 'textsize', get_string('textsize', 'organizer'),
                array('8' => get_string('font_small', 'organizer'), '10' => get_string('font_medium', 'organizer'),
                        '12' => get_string('font_large', 'organizer')));
        $textsize->setSelected('10');

        $pageorientation = $mform->addElement('select', 'pageorientation', get_string('pageorientation', 'organizer'),
                array('P' => get_string('orientationportrait', 'organizer'),
                        'L' => get_string('orientationlandscape', 'organizer')));
        $pageorientation->setSelected('L');

        $mform->addElement('advcheckbox', 'headerfooter', get_string('headerfooter', 'organizer'), null, null,
                array(0, 1));
        $mform->setType('headerfooter', PARAM_BOOL);
        $mform->setDefault('headerfooter', 1);

        $buttonarray = array();
        $buttonarray[] = &$mform->createElement('submit', 'pdfsubmit', get_string('pdfsubmit', 'organizer'));
        $buttonarray[] = &$mform->createElement('cancel', 'cancel', get_string('print_return', 'organizer'));

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);

        foreach ($selcols as $key => $selcol) {
            $mform->addElement('hidden', "cols[$key]", $selcol, array('id' => "col_{$selcol}"));
        }

    }

    public function display() {
        //finalize the form definition if not yet done
        if (!$this->_definition_finalized) {
            $this->_definition_finalized = true;
            $this->definition_after_data();
        }
        $this->_form->getValidationScript();
        $output = $this->_form->toHtml();
        $output .= '<div class="forced_scroll"><div style="float: left">';
        $output .= $this->_create_preview_table($this->_selcols);
        $output .= '</div><div style="width: 1em; float: left;"> </div></div>';
        print $output;
    }

    private function _create_preview_table($columns) {
        global $PAGE, $OUTPUT;
        
        $jsmodule = array(
                'name' => 'mod_organizer',
                'fullpath' => '/mod/organizer/module.js',
                'requires' => array('node', 'node-event-delegate'),
        );
        
        $PAGE->requires->js_init_call('M.mod_organizer.init_organizer_print_slots_form', null, false, $jsmodule);

        $table = new html_table();
        $table->id = 'print_preview';
        $table->attributes['class'] = 'boxaligncenter print-preview coloredrows';

        $header = array();
        foreach ($columns as $column) {
            $content = "<span name='{$column}_cell'>" . get_string("th_{$column}", 'organizer') . '</span>';
            $imgattr = array(
                    'src' => $OUTPUT->pix_url('t/switch_minus'),
                    'alt' => get_string('hide'), 
                    'id' => "toggle_{$column}",
                    'style' => 'cursor: pointer',
                    'title' => get_string("th_{$column}", 'organizer'));
            
            $content .= ' ' . html_writer::empty_tag('img', $imgattr);

            $cell = new html_table_cell($content);
            $cell->header = true;
            $header[] = $cell;
        }
        $table->head = $header;

        $data = &$this->_customdata;
        $entries = fetch_table_entries($data['slots']);

        $rows = array();
        $rowspan = 0;
        $numcols = 0;
        $evenodd = 0;
        foreach ($entries as $entry) {
            if ($numcols == 10) {
                break;
            }
            $row = $rows[] = new html_table_row();
            foreach ($columns as $column) {
                if ($rowspan == 0) {
                    switch ($column) {
                        case 'datetime':
                            $datetime = userdate($entry->starttime, get_string('fulldatetimetemplate', 'organizer'))
                                    . ' - '
                                    . userdate($entry->starttime + $entry->duration,
                                            get_string('timetemplate', 'organizer'));
                            $content = "<span name='{$column}_cell'>" . $datetime . '</span>';
                            $cell = new html_table_cell($content);
                            $cell->rowspan = $entry->rowspan;
                            $cell->style = 'vertical-align: middle;';
                            $row->cells[] = $cell;
                            break;
                        case 'location':
                            $location = $entry->location;
                            $content = "<span name='{$column}_cell'>" . $location . '</span>';
                            $cell = new html_table_cell($content);
                            $cell->rowspan = $entry->rowspan;
                            $cell->style = 'vertical-align: middle;';
                            $row->cells[] = $cell;
                            break;
                        case 'teacher':
                            $a = new stdClass();
                            $a->firstname = $entry->teacherfirstname;
                            $a->lastname = $entry->teacherlastname;
                            $name = get_string('fullname_template', 'organizer', $a);
                            $content = "<span name='{$column}_cell'>" . $name . '</span>';
                            $cell = new html_table_cell($content);
                            $cell->rowspan = $entry->rowspan;
                            $cell->style = 'vertical-align: middle;';
                            $row->cells[] = $cell;
                            break;
                        case 'groupname':
                            $groupname = $entry->groupname;
                            $content = "<span name='{$column}_cell'>" . $groupname . '</span>';
                            $cell = new html_table_cell($content);
                            $cell->rowspan = $entry->rowspan;
                            $cell->style = 'vertical-align: middle;';
                            $row->cells[] = $cell;
                            break;
                        default:
                            break;
                    }
                }

                switch ($column) {
                    case 'participant':
                        $a = new stdClass();
                        $a->firstname = $entry->firstname;
                        $a->lastname = $entry->lastname;
                        $name = get_string('fullname_template', 'organizer', $a);
                        $content = "<span name='{$column}_cell'>" . $name . '</span>';
                        $cell = new html_table_cell($content);
                        $cell->style = 'vertical-align: middle;';
                        $row->cells[] = $cell;
                        break;
                    case 'idnumber':
                        $idnumber = (isset($entry->idnumber) && $entry->idnumber !== '') ? $entry->idnumber : '';
                        $content = "<span name='{$column}_cell'>" . $idnumber . '</span>';
                        $cell = new html_table_cell($content);
                        $cell->style = 'vertical-align: middle;';
                        $row->cells[] = $cell;
                        break;
                    case 'attended':
                        $attended = isset($entry->attended) ? ($entry->attended == 1 ? 'Yes' : 'No') : '';
                        $content = "<span name='{$column}_cell'>" . $attended . '</span>';
                        $cell = new html_table_cell($content);
                        $cell->style = 'vertical-align: middle;';
                        $row->cells[] = $cell;
                        break;
                    case 'grade':
                        $grade = isset($entry->grade) ? sprintf("%01.2f", $entry->grade) : '';
                        $content = "<span name='{$column}_cell'>" . $grade . '</span>';
                        $cell = new html_table_cell($content);
                        $cell->style = 'vertical-align: middle;';
                        $row->cells[] = $cell;
                        break;
                    case 'feedback':
                        $feedback = isset($entry->feedback) && $entry->feedback !== '' ? $entry->feedback : '';
                        $content = "<span name='{$column}_cell'>" . $feedback . '</span>';
                        $cell = new html_table_cell($content);
                        $cell->style = 'vertical-align: middle;';
                        $row->cells[] = $cell;
                        break;
                    case 'datetime':
                    case 'location':
                    case 'teacher':
                    case 'groupname':
                        break;
                    default:
                        print_error("Unsupported column type: $column");
                }
            }
            $numcols++;
            $row->attributes['class'] = " r{$evenodd}";
            $rowspan = ($rowspan + 1) % $entry->rowspan;

            if ($rowspan == 0) {
                $evenodd = $evenodd ? 0 : 1;
            }
        }

        $table->data = $rows;

        return organizer_render_table_with_footer($table, false, true);
    }
}