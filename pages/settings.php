<?php

/**
 * Einstellungen: Frontend-Token, freigegebene Tabellen, Zeilenlimit,
 * Aufwertung von be_manager_relation-Feldern.
 */

$addon = rex_addon::get('relation_select');
$csrf = rex_csrf_token::factory('relation_select_token');

$message = '';
$action = rex_post('relation_select_token_action', 'string', '');
if ('' !== $action) {
    if (!$csrf->isValid()) {
        $message = rex_view::error(rex_i18n::msg('csrf_token_invalid'));
    } elseif ('regenerate' === $action) {
        rex_config::set('relation_select', 'api_token', bin2hex(random_bytes(32)));
        $message = rex_view::success(rex_i18n::msg('relation_select_token_regenerated'));
    } elseif ('remove' === $action) {
        rex_config::remove('relation_select', 'api_token');
        $message = rex_view::success(rex_i18n::msg('relation_select_token_removed'));
    }
}
echo $message;

// ---- Allgemeine Einstellungen ----
$form = rex_config_form::factory('relation_select');

$form->addFieldset(rex_i18n::msg('relation_select_settings_access_legend'));

// Freigabe per Relation Select selbst: alle Tabellen der Datenbank als Select-Quelle,
// gesperrte Tabellen (Zugangsdaten) tauchen gar nicht erst auf.
$field = $form->addSelectField('allowed_tables', null, ['data-relation-select' => 'inline', 'data-relation-format' => 'badge:kind']);
$field->setLabel(rex_i18n::msg('relation_select_settings_allowed_tables'));
$field->setNotice(rex_i18n::msg('relation_select_settings_allowed_tables_notice'));
$select = $field->getSelect();
$select->setMultiple();
$select->setSize(8);
$yformTables = [];
if (rex_addon::get('yform')->isAvailable()) {
    foreach (rex_yform_manager_table::getAll() as $yformTable) {
        $yformTables[$yformTable->getTableName()] = rex_i18n::translate($yformTable->getName());
    }
}
$corePrefix = rex::getTablePrefix();
foreach (rex_sql::showTables() as $tableName) {
    if (FriendsOfRedaxo\RelationSelect\TableAccess::isDeniedTable($tableName)) {
        continue;
    }
    $kind = isset($yformTables[$tableName]) ? 'YForm' : (str_starts_with($tableName, $corePrefix) ? '' : 'extern');
    $label = isset($yformTables[$tableName]) ? $tableName . ' – ' . $yformTables[$tableName] : $tableName;
    $select->addOption($label, $tableName, 0, 0, ['data-kind' => $kind]);
}

$field = $form->addInputField('number', 'max_rows', null, ['class' => 'form-control', 'min' => 0, 'max' => 100000]);
$field->setLabel(rex_i18n::msg('relation_select_settings_max_rows'));
$field->setNotice(rex_i18n::msg('relation_select_settings_max_rows_notice'));

$form->addFieldset(rex_i18n::msg('relation_select_settings_yform_legend'));

$field = $form->addSelectField('enhance_be_relation');
$field->setLabel(rex_i18n::msg('relation_select_settings_enhance'));
$field->setNotice(rex_i18n::msg('relation_select_settings_enhance_notice'));
$select = $field->getSelect();
$select->setSize(1);
$select->addOption(rex_i18n::msg('relation_select_settings_enhance_attribute'), 'attribute');
$select->addOption(rex_i18n::msg('relation_select_settings_enhance_multiple'), 'multiple');
$select->addOption(rex_i18n::msg('relation_select_settings_enhance_all'), 'all');
$select->addOption(rex_i18n::msg('relation_select_settings_enhance_off'), 'off');

$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', rex_i18n::msg('relation_select_settings'), false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');

// ---- Frontend-Token ----
$token = (string) rex_config::get('relation_select', 'api_token', '');

$body = '<p>' . rex_i18n::msg('relation_select_token_description') . '</p>';
if ('' !== $token) {
    $body .= '<div class="input-group">'
        . '<input type="text" class="form-control" id="relation-select-token" value="' . rex_escape($token) . '" readonly onclick="this.select();">'
        . '<span class="input-group-btn"><clipboard-copy for="relation-select-token" class="btn btn-default"><i class="rex-icon fa-clipboard"></i> ' . rex_i18n::msg('relation_select_copy_token') . '</clipboard-copy></span>'
        . '</div>';
} else {
    $body .= '<p class="text-muted">' . rex_i18n::msg('relation_select_token_none') . '</p>';
}
$body .= '<form method="post" action="' . rex_url::currentBackendPage() . '" class="rs-token-form">'
    . $csrf->getHiddenField()
    . '<button type="submit" name="relation_select_token_action" value="regenerate" class="btn btn-primary" data-confirm="' . rex_i18n::msg('relation_select_token_regenerate_confirm') . '">' . rex_i18n::msg('relation_select_token_regenerate') . '</button> '
    . ('' !== $token ? '<button type="submit" name="relation_select_token_action" value="remove" class="btn btn-danger" data-confirm="' . rex_i18n::msg('relation_select_token_remove_confirm') . '">' . rex_i18n::msg('relation_select_token_remove') . '</button>' : '')
    . '</form>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('relation_select_api_token'), false);
$fragment->setVar('body', $body, false);
echo $fragment->parse('core/page/section.php');
