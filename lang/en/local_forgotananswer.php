<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']              = 'Forgot an Answer';

// Admin settings.
$string['settings_heading']        = 'Popup visibility';
$string['settings_heading_desc']   = 'Control which roles see the "forgot an answer" popup during quiz attempts.';
$string['setting_enabled_roles']   = 'Show popup for roles';
$string['setting_enabled_roles_desc'] = 'Tick every role that should see the popup. If no roles are ticked, the popup is not shown to anyone.';

// Modal strings (injected into JavaScript).
$string['modal_title']             = 'Woops, you forgot an answer.';
$string['modal_body_single']       = "We didn't register an answer in";
$string['modal_body_multiple']     = "We didn't register answers in";
$string['modal_question']          = 'question';
$string['modal_ok']                = 'OK';
