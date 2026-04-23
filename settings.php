<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $allroles = role_get_names(null, ROLENAME_ALIAS, false);
    $choices = [];
    foreach ($allroles as $role) {
        $choices[$role->id] = $role->localname;
    }

    $settings->add(new admin_setting_heading(
        'local_forgotananswer/heading',
        get_string('settings_heading', 'local_forgotananswer'),
        get_string('settings_heading_desc', 'local_forgotananswer')
    ));

    $settings->add(new admin_setting_configmulticheckbox(
        'local_forgotananswer/enabled_roles',
        get_string('setting_enabled_roles', 'local_forgotananswer'),
        get_string('setting_enabled_roles_desc', 'local_forgotananswer'),
        [],
        $choices
    ));
}
