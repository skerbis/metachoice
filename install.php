<?php

$addon = rex_addon::get('relation_select');

if (!rex_config::has('relation_select', 'api_token')) {
    $token = bin2hex(random_bytes(32));
    rex_config::set('relation_select', 'api_token', $token);
    $addon->setProperty('successmsg', $addon->i18n('install_success') . ' ' . $addon->i18n('install_token_msg'));
}
