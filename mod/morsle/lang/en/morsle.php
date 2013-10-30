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
 * Strings for component 'morsle', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package    mod
 * @subpackage morsle
 * @copyright  
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['clicktoopen'] = 'Click {$a} link to open resource.';
$string['configdisplayoptions'] = 'Select all options that should be available, existing settings are not modified. Hold CTRL key to select multiple fields.';
$string['configframesize'] = 'When a web page or an uploaded file is displayed within a frame, this value is the height (in pixels) of the top frame (which contains the navigation).';
$string['configrolesinparams'] = 'Enable if you want to include localized role names in list of available parameter variables.';
$string['configsecretphrase'] = 'This secret phrase is used to produce encrypted code value that can be sent to some servers as a parameter.  The encrypted code is produced by an md5 value of the current user IP address concatenated with your secret phrase. ie code = md5(IP.secretphrase). Please note that this is not reliable because IP address may change and is often shared by different computers.';
$string['contentheader'] = 'Content';
$string['createmorsle'] = 'Create a morsle';
$string['displayoptions'] = 'Available display options';
$string['displayselect'] = 'When clicked, how should this link display?';
$string['displayselect_help'] = 'This setting, together with the morsle file type and whether the browser allows embedding, determines how the morsle is displayed. Options may include:

* Automatic - The best display option for the morsle is selected automatically
* Embed - The morsle is displayed within the page below the navigation bar together with the morsle description and any blocks
* Force download - The user is prompted to download the morsle file
* Open - Only the morsle is displayed in the browser window
* In pop-up - The morsle is displayed in a new browser window without menus or an address bar
* In frame - The morsle is displayed within a frame below the the navigation bar and morsle description
* New window - The morsle is displayed in a new browser window with menus and an address bar';
$string['displayselectexplain'] = 'Choose display type, unfortunately not all types are suitable for all morsles.';
$string['externalurl'] = 'External url';
$string['framesize'] = 'Frame height';
$string['invalidstoredurl'] = 'Cannot display this resource, URL is invalid.';
$string['chooseavariable'] = 'Choose a variable...';
$string['idnumbermod'] = 'Unique ID if using grading calculations (not common)';
$string['invalidurl'] = 'Entered URL is invalid';
$string['modulename'] = 'morsle';
$string['modulename_help'] = 'The Morsle module enables a teacher to provide a google doc link as a course resource. Any file in their course, user or departmental google accounts can be linked to. The Morsle repository will automatically be made available in the file picker facilitating easy linking.  Permissions to access the resource by the user clicking on it are handled automatically.

There are a number of display options for the morsle, such as embedded or opening in a new window and advanced options for passing information, such as a student\'s name, to the URL if required.';
$string['modulename_link'] = 'mod/morsle/view';
$string['modulenameplural'] = 'morsles';
$string['namenotrequired'] = 'Name<br /> (optional), will be filled by name of resource';
$string['neverseen'] = 'Never seen';
$string['optionsheader'] = 'Options';
$string['page-mod-morsle-x'] = 'Any morsle module page';
$string['parameterinfo'] = '&amp;parameter=variable';
$string['parametersheader'] = 'Parameters';
$string['parametersheader_help'] = 'Some internal Moodle variables may be automatically appended to the URL. Type your name for the parameter into each text box(es) and then select the required matching variable.';
$string['pluginadministration'] = 'morsle module administration';
$string['pluginname'] = 'morsle';
$string['popupheight'] = 'If display = "Popup", height (in pixels)';
$string['popupheightexplain'] = 'Specifies default height of popup windows.';
$string['popupwidth'] = 'If display = "Popup", width (in pixels)';
$string['popupwidthexplain'] = 'Specifies default width of popup windows.';
$string['printheading'] = 'Display morsle name';
$string['printheadingexplain'] = 'Display URL name above content? Some display types may not display URL name even if enabled.';
$string['printintro'] = 'Display URL description';
$string['printintroexplain'] = 'Display URL description below content? Some display types may not display description even if enabled.';
$string['rolesinparams'] = 'Include role names in parameters';
$string['serverurl'] = 'Server URL';
$string['morsle:addinstance'] = 'Add a new morsle resource';
$string['morsle:view'] = 'View morsle';
$string['visible'] = 'Visible to students?';
