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
 * morsle module admin settings and defaults
 *
 * @package    mod
 * @subpackage morsle
 * @copyright  2009 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once("$CFG->libdir/resourcelib.php");

    $displayoptions = resourcelib_get_displayoptions(array(RESOURCELIB_DISPLAY_AUTO,
                                                           RESOURCELIB_DISPLAY_EMBED,
                                                           RESOURCELIB_DISPLAY_FRAME,
                                                           RESOURCELIB_DISPLAY_OPEN,
                                                           RESOURCELIB_DISPLAY_NEW,
                                                           RESOURCELIB_DISPLAY_POPUP,
                                                          ));
    $defaultdisplayoptions = array(RESOURCELIB_DISPLAY_AUTO,
                                   RESOURCELIB_DISPLAY_EMBED,
                                   RESOURCELIB_DISPLAY_OPEN,
                                   RESOURCELIB_DISPLAY_POPUP,
                                  );

    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_configtext('morsle/framesize',
        get_string('framesize', 'morsle'), get_string('configframesize', 'morsle'), 130, PARAM_INT));
    $settings->add(new admin_setting_configcheckbox('morsle/requiremodintro',
        get_string('requiremodintro', 'admin'), get_string('configrequiremodintro', 'admin'), 1));
    $settings->add(new admin_setting_configpasswordunmask('morsle/secretphrase', get_string('password'),
        get_string('configsecretphrase', 'morsle'), ''));
    $settings->add(new admin_setting_configcheckbox('morsle/rolesinparams',
        get_string('rolesinparams', 'morsle'), get_string('configrolesinparams', 'morsle'), false));
    $settings->add(new admin_setting_configmultiselect('morsle/displayoptions',
        get_string('displayoptions', 'morsle'), get_string('configdisplayoptions', 'morsle'),
        $defaultdisplayoptions, $displayoptions));

    //--- modedit defaults -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('morslemodeditdefaults', get_string('modeditdefaults', 'admin'), get_string('condifmodeditdefaults', 'admin')));

    $settings->add(new admin_setting_configcheckbox_with_advanced('morsle/printheading',
        get_string('printheading', 'morsle'), get_string('printheadingexplain', 'morsle'),
        array('value'=>0, 'adv'=>false)));
    $settings->add(new admin_setting_configcheckbox_with_advanced('morsle/printintro',
        get_string('printintro', 'morsle'), get_string('printintroexplain', 'morsle'),
        array('value'=>1, 'adv'=>false)));
    $settings->add(new admin_setting_configselect_with_advanced('morsle/display',
        get_string('displayselect', 'morsle'), get_string('displayselectexplain', 'morsle'),
        array('value'=>RESOURCELIB_DISPLAY_AUTO, 'adv'=>false), $displayoptions));
    $settings->add(new admin_setting_configtext_with_advanced('morsle/popupwidth',
        get_string('popupwidth', 'morsle'), get_string('popupwidthexplain', 'morsle'),
        array('value'=>620, 'adv'=>true), PARAM_INT, 7));
    $settings->add(new admin_setting_configtext_with_advanced('morsle/popupheight',
        get_string('popupheight', 'morsle'), get_string('popupheightexplain', 'morsle'),
        array('value'=>450, 'adv'=>true), PARAM_INT, 7));
}
