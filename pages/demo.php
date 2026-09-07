<?php

/**
 * Demo: alle Aufrufwege mit echten Daten der Installation. Es wird nichts
 * gespeichert, die aktuellen Werte werden nur unter dem Widget angezeigt.
 */

$articleConfig = json_encode([
    'table' => 'rex_article',
    'valueField' => 'id',
    'labelField' => 'name',
    'displayFields' => 'badge:status|(id)',
    'displayFormat' => 'badge:status|(id)',
    'dbob' => 'name,ASC',
    'clang' => rex_clang::getStartId(),
], JSON_THROW_ON_ERROR);

$mediaConfig = json_encode([
    'table' => 'rex_media',
    'valueField' => 'id',
    'labelField' => 'title|filename',
    'displayFields' => 'badge:filetype',
    'displayFormat' => 'badge:filetype',
    'dbob' => 'filename,ASC',
], JSON_THROW_ON_ERROR);

$demoArticles = rex_sql::factory()->getArray('SELECT id, name FROM ' . rex::getTable('article') . ' WHERE clang_id = ? ORDER BY name LIMIT 12', [rex_clang::getStartId()]);
$preselect = implode(',', array_map(static fn (array $row): string => (string) $row['id'], array_slice($demoArticles, 0, 2)));
$mediaPreselect = implode(',', array_map(static fn (array $row): string => (string) $row['id'], rex_sql::factory()->getArray('SELECT id FROM ' . rex::getTable('media') . ' ORDER BY id LIMIT 2')));

$sections = [];

// 1. Inline
$sections[] = [
    'title' => rex_i18n::msg('relation_select_demo_inline'),
    'intro' => rex_i18n::msg('relation_select_demo_inline_intro'),
    'html' => '<input type="text" class="form-control" name="demo_inline" value="' . rex_escape($preselect) . '" data-relation-config="' . rex_escape($articleConfig) . '">',
    'code' => '<input type="text" name="REX_INPUT_VALUE[1]" value="REX_VALUE[1]"' . "\n" .
        '    data-relation-config=\'' . $articleConfig . '\'>',
];

// 2. Modal
$sections[] = [
    'title' => rex_i18n::msg('relation_select_demo_modal'),
    'intro' => rex_i18n::msg('relation_select_demo_modal_intro'),
    'html' => '<input type="text" class="form-control" name="demo_modal" value="' . rex_escape($mediaPreselect) . '" data-relation-mode="modal" data-relation-config="' . rex_escape($mediaConfig) . '">',
    'code' => '<input type="text" name="REX_INPUT_VALUE[2]" value="REX_VALUE[2]"' . "\n" .
        '    data-relation-mode="modal"' . "\n" .
        '    data-relation-config=\'' . $mediaConfig . '\'>',
];

// 3. Einzelauswahl
$sections[] = [
    'title' => rex_i18n::msg('relation_select_demo_single'),
    'intro' => rex_i18n::msg('relation_select_demo_single_intro'),
    'html' => '<input type="text" class="form-control" name="demo_single" value="" data-relation-multiple="0" data-relation-config="' . rex_escape($articleConfig) . '">',
    'code' => '<input type="text" name="REX_INPUT_VALUE[3]" value="REX_VALUE[3]"' . "\n" .
        '    data-relation-multiple="0"' . "\n" .
        '    data-relation-config=\'' . $articleConfig . '\'>',
];

// 4. Select-Aufwertung (be_manager_relation)
$options = '';
foreach ($demoArticles as $i => $row) {
    $options .= '<option value="' . (int) $row['id'] . '"' . ($i < 2 ? ' selected' : '') . '>' . rex_escape($row['name']) . '</option>';
}
$sections[] = [
    'title' => rex_i18n::msg('relation_select_demo_select'),
    'intro' => rex_i18n::msg('relation_select_demo_select_intro'),
    'html' => '<select class="form-control" name="demo_select[]" multiple data-relation-select="inline">' . $options . '</select>',
    'code' => '<select name="tags[]" multiple data-relation-select="inline">' . "\n" .
        '    <option value="1">Release</option>' . "\n" .
        '    <option value="2" selected>AddOn</option>' . "\n" .
        '</select>' . "\n\n" .
        '// YForm be_manager_relation: Individuelle Attribute' . "\n" .
        '{"data-relation-select": "modal"}',
];

foreach ($sections as $i => $section) {
    $body = '<p>' . $section['intro'] . '</p>'
        . '<div class="rs-demo-field">' . $section['html'] . '</div>'
        . '<p class="rs-demo-value"><small>' . rex_i18n::msg('relation_select_demo_value') . ': <code class="rs-demo-current">–</code></small></p>'
        . '<details><summary>' . rex_i18n::msg('relation_select_demo_code') . '</summary><pre><code>' . rex_escape($section['code']) . '</code></pre></details>';

    $fragment = new rex_fragment();
    $fragment->setVar('title', ($i + 1) . '. ' . $section['title'], false);
    $fragment->setVar('body', $body, false);
    echo $fragment->parse('core/page/section.php');
}
?>
<script nonce="<?= rex_response::getNonce() ?>">
(function () {
    function show(field) {
        var box = field.closest('.panel') && field.closest('.panel').querySelector('.rs-demo-current');
        if (!box) return;
        var value = field.tagName === 'SELECT'
            ? Array.prototype.filter.call(field.options, function (o) { return o.selected; }).map(function (o) { return o.value; }).join(',')
            : field.value;
        box.textContent = value === '' ? '–' : value;
    }
    document.querySelectorAll('.rs-demo-field input, .rs-demo-field select').forEach(function (field) {
        show(field);
        field.addEventListener('change', function () { show(field); });
    });
})();
</script>
