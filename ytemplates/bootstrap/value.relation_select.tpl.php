<?php

/**
 * YForm Bootstrap-Template: Relation Select
 *
 * @var rex_yform_value_relation_select $this
 * @psalm-scope-this rex_yform_value_relation_select
 * @var string $table
 * @var string $value_field
 * @var string $label_field
 * @var string $display_fields
 * @var string $filter
 * @var string $order_by
 * @var bool $multiple
 */

$class_group = trim('form-group ' . $this->getHTMLClass() . ' ' . $this->getWarningClass());

$notice = [];
if ('' !== (string) $this->getElement('notice')) {
    $notice[] = rex_i18n::translate($this->getElement('notice'), false);
}
if (isset($this->params['warning_messages'][$this->getId()]) && !$this->params['hide_field_warning_messages']) {
    $notice[] = '<span class="text-warning">' . rex_i18n::translate($this->params['warning_messages'][$this->getId()], false) . '</span>';
}
$noticeHtml = '' !== implode('', $notice)
    ? '<p class="help-block small">' . implode('<br />', $notice) . '</p>'
    : '';

// Relation-Config für das JS-Widget aufbauen
$config = [
    'table'      => $table,
    'valueField' => $value_field,
    'labelField' => $label_field,
];
if ('' !== $display_fields) {
    $config['displayFields'] = $display_fields;
    // displayFormat nutzt dieselbe Syntax (badge:x|color:x) für das JS-Rendering
    $config['displayFormat'] = $display_fields;
}
if ('' !== $filter) {
    $config['dbw'] = $filter;
}
if ('' !== $order_by) {
    $config['dbob'] = $order_by;
}

$attributes = json_decode((string) $this->getElement('attributes'), true);
if (!is_array($attributes)) {
    $attributes = [];
}
$relationMode = (string) ($attributes['data-relation-mode'] ?? 'inline');

$inputId  = $this->getFieldId();
$inputName = $this->getFieldName();
$currentValue = (string) $this->getValue();
?>
<div class="<?= $class_group ?>" id="<?= $this->getHTMLId() ?>">
    <label class="control-label" for="<?= rex_escape($inputId) ?>"><?= $this->getLabel() ?></label>
    <input
        type="hidden"
        id="<?= rex_escape($inputId) ?>"
        name="<?= rex_escape($inputName) ?>"
        value="<?= rex_escape($currentValue) ?>"
        data-relation-config="<?= rex_escape(json_encode($config)) ?>"
        data-relation-mode="<?= rex_escape($relationMode) ?>"
        data-relation-multiple="<?= $multiple ? '1' : '0' ?>"
    >
    <?= $noticeHtml ?>
</div>
