<?php

use FriendsOfRedaxo\RelationSelect\RelationSelect;

$addon = rex_addon::get('relation_select');

rex_api_function::register('relation_select', RelationSelect::class);

if (rex::isBackend() && 'login' !== rex_be_controller::getCurrentPage()) {
    // Pro-Datei-Cache-Buster, damit Browser nach Updates keine alten Assets nutzen
    $bust = static fn (string $file): string => '?v=' . filemtime($addon->getPath('assets/' . $file));
    rex_view::addJsFile($addon->getAssetsUrl('relation_select.js') . $bust('relation_select.js'));
    rex_view::addCssFile($addon->getAssetsUrl('relation_select.css') . $bust('relation_select.css'));

    rex_view::setJsProperty('relation_select', [
        'enhance' => (string) $addon->getConfig('enhance_be_relation', 'attribute'),
        'i18n' => [
            'search_placeholder' => rex_i18n::msg('relation_select_search_placeholder'),
            'available_items' => rex_i18n::msg('relation_select_available_items'),
            'selected_items' => rex_i18n::msg('relation_select_selected_items'),
            'add' => rex_i18n::msg('relation_select_add'),
            'add_all' => rex_i18n::msg('relation_select_add_all'),
            'remove' => rex_i18n::msg('relation_select_remove'),
            'clear_all' => rex_i18n::msg('relation_select_clear_all'),
            'sort' => rex_i18n::msg('relation_select_sort'),
            'choose' => rex_i18n::msg('relation_select_choose'),
            'modal_title' => rex_i18n::msg('relation_select_modal_title'),
            'cancel' => rex_i18n::msg('relation_select_cancel'),
            'apply' => rex_i18n::msg('relation_select_apply'),
            'no_results' => rex_i18n::msg('relation_select_no_results'),
            'empty_selection' => rex_i18n::msg('relation_select_empty_selection'),
            'error_loading' => rex_i18n::msg('relation_select_error_loading'),
            'online' => rex_i18n::msg('relation_select_online'),
            'offline' => rex_i18n::msg('relation_select_offline'),
        ],
    ]);
}

if (rex_addon::get('yform')->isAvailable()) {
    rex_yform::addTemplatePath($addon->getPath('ytemplates'));
}
