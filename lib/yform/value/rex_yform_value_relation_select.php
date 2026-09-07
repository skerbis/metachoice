<?php

/**
 * YForm Value: Relation Select
 *
 * Rendert das Relation Select Widget in YForm-Formularen.
 * Unterstützt Suche in der YForm Table Manager Liste.
 *
 * @package relation_select
 */
class rex_yform_value_relation_select extends rex_yform_value_abstract
{
    public function enterObject(): void
    {
        $value = (string) ($this->getValue() ?? '');
        $this->setValue($value);

        $this->params['value_pool']['email'][$this->getName()] = $value;

        if ($this->saveInDb()) {
            $this->params['value_pool']['sql'][$this->getName()] = $value;
        }

        if ($this->needsOutput() && $this->isViewable()) {
            $multiple = (bool) $this->getElement('multiple');

            $cfg = self::resolveConfig(
                (string) $this->getElement('table'),
                (string) ($this->getElement('value_field') ?: 'id'),
                (string) $this->getElement('label_field'),
                (string) $this->getElement('display_fields'),
                (string) $this->getElement('filter'),
                (string) $this->getElement('order_by'),
                (string) $this->getElement('attributes'),
            );

            $this->params['form_output'][$this->getId()] = $this->parse(
                'value.relation_select.tpl.php',
                [
                    'table'          => $cfg['table'],
                    'value_field'    => $cfg['value_field'],
                    'label_field'    => $cfg['label_field'],
                    'display_fields' => $cfg['display_fields'],
                    'filter'         => $cfg['filter'],
                    'order_by'       => $cfg['order_by'],
                    'multiple'       => $multiple,
                ],
            );
        }
    }

    public function getDescription(): string
    {
        return 'relation_select|name|label|table|value_field|label_field|[display_fields]|[filter]|[order_by]|[multiple]';
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefinitions(): array
    {
        return [
            'type'        => 'value',
            'name'        => 'relation_select',
            'values'      => [
                'name'           => ['type' => 'name',    'label' => rex_i18n::msg('yform_values_defaults_name')],
                'label'          => ['type' => 'text',    'label' => rex_i18n::msg('yform_values_defaults_label')],
                'table'          => ['type' => 'text',    'label' => rex_i18n::msg('relation_select_yform_table'),
                    'notice' => rex_i18n::msg('relation_select_yform_table_notice')],
                'value_field'    => ['type' => 'text',    'label' => rex_i18n::msg('relation_select_yform_value_field'),
                    'default' => 'id',
                    'notice' => rex_i18n::msg('relation_select_yform_value_field_notice')],
                'label_field'    => ['type' => 'text',    'label' => rex_i18n::msg('relation_select_yform_label_field'),
                    'notice' => rex_i18n::msg('relation_select_yform_label_field_notice')],
                'display_fields' => ['type' => 'text',    'label' => rex_i18n::msg('relation_select_yform_display_fields'),
                    'notice' => rex_i18n::msg('relation_select_yform_display_fields_notice')],
                'filter'         => ['type' => 'text',    'label' => rex_i18n::msg('relation_select_yform_filter'),
                    'notice' => rex_i18n::msg('relation_select_yform_filter_notice')],
                'order_by'       => ['type' => 'text',    'label' => rex_i18n::msg('relation_select_yform_order_by'),
                    'notice' => rex_i18n::msg('relation_select_yform_order_by_notice')],
                'multiple'       => ['type' => 'boolean', 'label' => rex_i18n::msg('relation_select_yform_multiple'),
                    'default' => 1],
                'attributes'     => ['type' => 'text',    'label' => rex_i18n::msg('yform_values_defaults_attributes'),
                    'notice' => rex_i18n::msg('relation_select_yform_attributes_notice')],
                'notice'         => ['type' => 'text',    'label' => rex_i18n::msg('yform_values_defaults_notice')],
            ],
            'description' => rex_i18n::msg('relation_select_yform_description'),
            'db_type'     => ['text', 'varchar(191)'],
            'famous'      => false,
        ];
    }

    /**
     * Löst die Feldkonfiguration auf. Sind die neuen Felder leer, wird als Fallback
     * das attributes-JSON auf einen data-relation-config-Key geprüft.
     *
     * @return array{table:string, value_field:string, label_field:string, display_fields:string, filter:string, order_by:string}
     */
    private static function resolveConfig(
        string $table,
        string $valueField,
        string $labelField,
        string $displayFields,
        string $filter,
        string $orderBy,
        string $rawAttributes,
    ): array {
        if ('' === $table) {
            $attrs = json_decode($rawAttributes, true);
            if (is_array($attrs) && isset($attrs['data-relation-config'])) {
                $cfg = json_decode($attrs['data-relation-config'], true);
                if (is_array($cfg)) {
                    $table         = (string) ($cfg['table'] ?? '');
                    $valueField    = (string) ($cfg['valueField'] ?? $cfg['value_field'] ?? $valueField ?: 'id');
                    $labelField    = (string) ($cfg['labelField'] ?? $cfg['label_field'] ?? $labelField);
                    $displayFields = (string) ($cfg['displayFields'] ?? $cfg['display_fields'] ?? $displayFields);
                    $filter        = (string) ($cfg['dbw'] ?? $cfg['filter'] ?? $filter);
                    $orderBy       = (string) ($cfg['dbob'] ?? $cfg['order_by'] ?? $orderBy);
                }
            }
        }

        return [
            'table'          => $table,
            'value_field'    => '' !== $valueField ? $valueField : 'id',
            'label_field'    => $labelField,
            'display_fields' => $displayFields,
            'filter'         => $filter,
            'order_by'       => $orderBy,
        ];
    }

    /**
     * Tabelle und Spalten muessen gueltige Bezeichner sein, Zugangsdaten-Tabellen sind tabu.
     *
     * @param list<string> $labelFields
     */
    private static function identifiersValid(string $table, string $valueField, array $labelFields): bool
    {
        $access = \FriendsOfRedaxo\RelationSelect\TableAccess::class;
        if (!$access::isValidIdentifier($table) || $access::isDeniedTable($table) || !$access::isValidIdentifier($valueField)) {
            return false;
        }
        foreach ($labelFields as $field) {
            if (!$access::isValidIdentifier($field) || $access::isDeniedColumn($field)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Lädt alle Einträge aus der Relation-Tabelle und gibt sie als Choices-Array zurück.
     *
     * @param rex_yform_manager_field $field
     * @return array<string, string>
     */
    private static function buildChoicesArray(rex_yform_manager_field $field): array
    {
        $cfg        = self::resolveConfig(
            (string) $field->getElement('table'),
            (string) ($field->getElement('value_field') ?: 'id'),
            (string) $field->getElement('label_field'),
            (string) $field->getElement('display_fields'),
            (string) $field->getElement('filter'),
            (string) $field->getElement('order_by'),
            (string) $field->getElement('attributes'),
        );
        $table       = $cfg['table'];
        $valueField  = $cfg['value_field'];
        $labelFields = array_values(array_filter(array_map('trim', explode('|', $cfg['label_field']))));
        $orderBy     = $cfg['order_by'];
        $filter      = $cfg['filter'];

        if ('' === $table || [] === $labelFields || !self::identifiersValid($table, $valueField, $labelFields)) {
            return [];
        }

        try {
            $sql = rex_sql::factory();

            $labelExpr = count($labelFields) > 1
                ? 'CONCAT(' . implode(", ' ', ", array_map([$sql, 'escapeIdentifier'], $labelFields)) . ')'
                : $sql->escapeIdentifier($labelFields[0]);

            $query = 'SELECT '
                . $sql->escapeIdentifier($valueField) . ' AS val, '
                . $labelExpr . ' AS lbl '
                . 'FROM ' . $sql->escapeIdentifier($table);

            // Gleiche Filtersyntax wie das Widget (dbw): Bedingungen kommasepariert, Werte gebunden
            $where = \FriendsOfRedaxo\RelationSelect\Filter::parseWhere($filter);
            if ([] !== $where['sql']) {
                $query .= ' WHERE ' . implode(' AND ', $where['sql']);
            }

            $order = \FriendsOfRedaxo\RelationSelect\Filter::parseOrder($orderBy);
            if ([] !== $order['sql']) {
                $query .= ' ORDER BY ' . implode(', ', $order['sql']);
            }

            $sql->setQuery($query, $where['params']);
            $choices = [];
            while ($sql->hasNext()) {
                $val = (string) $sql->getValue('val');
                $lbl = (string) $sql->getValue('lbl');
                $choices[$val] = $lbl;
                $sql->next();
            }

            return $choices;
        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);
            return [];
        }
    }

    /**
     * Suchfeld: Einfaches Dropdown mit allen Einträgen aus der Relation-Tabelle.
     * Ermöglicht die Filterung nach einem einzelnen Datensatz.
     *
     * @param array<string, mixed> $params
     */
    public static function getSearchField(array $params): void
    {
        /** @var rex_yform_manager_field $field */
        $field   = $params['field'];
        $choices = self::buildChoicesArray($field);

        // Leerer Eintrag am Anfang erlaubt das Zurücksetzen des Suchfilters
        $choicesWithEmpty = ['' => rex_i18n::msg('relation_select_search_empty_option')] + $choices;

        $params['searchForm']->setValueField('choice', [
            'name'     => $field->getName(),
            'label'    => $field->getLabel(),
            'expanded' => false,
            'multiple' => false,
            'no_db'    => true,
            'choices'  => $choicesWithEmpty,
        ]);
    }

    /**
     * Suchfilter: Filtert anhand des gewählten Wertes im Dropdown.
     * Da das Feld kommaseparierte IDs speichert, wird whereListContains verwendet.
     *
     * @param array<string, mixed> $params
     * @return rex_yform_manager_query<rex_yform_manager_dataset>
     */
    public static function getSearchFilter(array $params): rex_yform_manager_query
    {
        $value = trim((string) $params['value']);

        /** @var rex_yform_manager_query<rex_yform_manager_dataset> $query */
        $query = $params['query'];

        if ('' === $value) {
            return $query;
        }

        $fieldName = $query->getTableAlias() . '.' . $params['field']->getName();

        return $query->whereListContains($fieldName, $value);
    }

    /**
     * Listendarstellung: Bei einem Datensatz dessen Label, bei mehreren nur den Zähler.
     *
     * @param array<string, mixed> $params
     */
    public static function getListValue(array $params): string
    {
        $field = $params['params']['field'];
        $value = (string) ($params['value'] ?? '');

        if ('' === $value) {
            return '';
        }

        $ids = array_values(array_filter(array_map('trim', explode(',', $value))));

        if ([] === $ids) {
            return '';
        }

        $total = count($ids);

        // Mehr als ein Eintrag: nur Anzahl als Badge
        if ($total > 1) {
            return '<span style="display:inline-block;background:var(--color-brand,#20aded);color:#fff;border-radius:10px;padding:1px 8px;font-size:0.85em;white-space:nowrap;">'
                . $total . ' ' . rex_i18n::msg('relation_select_list_records')
                . '</span>';
        }

        // Genau ein Eintrag: Label aus der Datenbank laden
        $cfg         = self::resolveConfig(
            (string) ($field['table'] ?? ''),
            (string) ($field['value_field'] ?: 'id'),
            (string) ($field['label_field'] ?? ''),
            (string) ($field['display_fields'] ?? ''),
            (string) ($field['filter'] ?? ''),
            (string) ($field['order_by'] ?? ''),
            (string) ($field['attributes'] ?? ''),
        );
        $table       = $cfg['table'];
        $valueField  = $cfg['value_field'];
        $labelFields = array_values(array_filter(array_map('trim', explode('|', $cfg['label_field']))));

        if ('' === $table || [] === $labelFields || !self::identifiersValid($table, $valueField, $labelFields)) {
            return rex_escape($ids[0]);
        }

        try {
            $sql = rex_sql::factory();

            $labelExpr = count($labelFields) > 1
                ? 'CONCAT(' . implode(", ' ', ", array_map([$sql, 'escapeIdentifier'], $labelFields)) . ')'
                : $sql->escapeIdentifier($labelFields[0]);

            $sql->setQuery(
                'SELECT ' . $labelExpr . ' AS lbl'
                . ' FROM ' . $sql->escapeIdentifier($table)
                . ' WHERE ' . $sql->escapeIdentifier($valueField) . ' = ' . $sql->escape($ids[0])
                . ' LIMIT 1',
            );

            if ($sql->hasNext()) {
                return rex_escape((string) $sql->getValue('lbl'));
            }

            return rex_escape($ids[0]);
        } catch (rex_sql_exception $e) {
            rex_logger::logException($e);
            return rex_escape($ids[0]);
        }
    }
}
