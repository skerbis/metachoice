<?php

namespace FriendsOfRedaxo\RelationSelect;

use rex;
use rex_api_function;
use rex_clang;
use rex_config;
use rex_response;
use rex_sql;
use rex_sql_exception;

use function count;
use function in_array;

/**
 * Liefert die Datensaetze einer Tabelle als JSON fuer das Widget.
 *
 * GET index.php?rex-api-call=relation_select&table=rex_article&value_field=id&label_field=name
 *     [&display_fields=badge:status|color:art_color][&dbw=status = 1][&dbob=name,ASC]
 *     [&clang=1][&values=1,5,9][&token=...]
 *
 * Zugriff: eingeloggter Backend-User (Tabellenrechte, siehe TableAccess) oder
 * Frontend-Token (nur freigegebene Tabellen). Spalten muessen existieren,
 * Spalten mit Zugangsdaten sind gesperrt. "values" liefert die bereits
 * ausgewaehlten Datensaetze auch dann, wenn sie der Filter oder das
 * Zeilenlimit ausschliessen wuerde.
 */
class RelationSelect extends rex_api_function
{
    protected $published = true;

    public function execute(): never
    {
        rex_response::cleanOutputBuffers();

        $user = rex::getUser();
        if (null === $user) {
            $token = rex_get('token', 'string', '');
            $configured = (string) rex_config::get('relation_select', 'api_token', '');
            if ('' === $token || '' === $configured || !hash_equals($configured, $token)) {
                $this->fail(rex_response::HTTP_UNAUTHORIZED, 'Access denied');
            }
        }

        $table = strtolower(trim(rex_get('table', 'string', '')));
        $valueField = trim(rex_get('value_field', 'string', ''));
        $labelField = trim(rex_get('label_field', 'string', ''));
        $displayFields = trim(rex_get('display_fields', 'string', ''));
        $clang = rex_get('clang', 'int', 0);
        $dbWhere = trim(rex_get('dbw', 'string', ''));
        $dbOrderBy = trim(rex_get('dbob', 'string', ''));
        $values = trim(rex_get('values', 'string', ''));

        if ('' === $table || '' === $valueField || '' === $labelField) {
            $this->fail(rex_response::HTTP_BAD_REQUEST, 'Missing parameters');
        }
        if (!TableAccess::tableExists($table)) {
            $this->fail(rex_response::HTTP_BAD_REQUEST, 'Unknown table');
        }
        if (!TableAccess::isAllowed($table, $user)) {
            $this->fail(rex_response::HTTP_FORBIDDEN, 'Permission denied for table');
        }

        $where = Filter::parseWhere($dbWhere);
        $order = Filter::parseOrder($dbOrderBy);
        $labelFields = Filter::fieldNames($labelField);
        $additionalFields = Filter::fieldNames($displayFields);
        $this->assertColumns($table, array_merge([$valueField], $labelFields, $additionalFields, $where['fields'], $order['fields']));

        $sql = rex_sql::factory();
        $selectFields = [$sql->escapeIdentifier($valueField) . ' AS value', $this->labelExpression($labelFields, $labelField, $clang)];
        foreach (array_unique($additionalFields) as $field) {
            if ($field !== $valueField) {
                $selectFields[] = $sql->escapeIdentifier($field);
            }
        }
        $from = 'SELECT DISTINCT ' . implode(', ', $selectFields) . ' FROM ' . $sql->escapeIdentifier($table);

        $conditions = $where['sql'];
        $params = $where['params'];
        // Sprachfilter fuer mehrsprachige Core-Tabellen
        if ($clang > 0 && in_array($table, ['rex_article', 'rex_article_slice'], true)) {
            $conditions[] = 'clang_id = ?';
            $params[] = (string) $clang;
        }

        $query = $from;
        if ([] !== $conditions) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $query .= ' ORDER BY ' . ([] !== $order['sql'] ? implode(', ', $order['sql']) : 'label');
        $maxRows = (int) rex_config::get('relation_select', 'max_rows', 0);
        if ($maxRows > 0) {
            $query .= ' LIMIT ' . $maxRows;
        }

        try {
            $rows = rex_sql::factory()->getArray($query, $params);

            // Bereits ausgewaehlte Werte immer mitliefern (unabhaengig von Filter/Limit)
            $selectedValues = array_values(array_filter(array_map('trim', explode(',', $values)), static fn (string $v): bool => '' !== $v));
            if ([] !== $selectedValues) {
                $known = array_map(static fn (array $row): string => (string) $row['value'], $rows);
                $missing = array_values(array_diff($selectedValues, $known));
                if ([] !== $missing) {
                    $in = implode(', ', array_fill(0, count($missing), '?'));
                    $extra = rex_sql::factory()->getArray($from . ' WHERE ' . $sql->escapeIdentifier($valueField) . ' IN (' . $in . ')', $missing);
                    $rows = array_merge($rows, $extra);
                }
            }
        } catch (rex_sql_exception $e) {
            $this->fail(rex_response::HTTP_BAD_REQUEST, 'Query failed');
        }

        // Im Frontend kann vor dem API-Aufruf bereits ein 404 (fehlender Artikel) gesetzt sein
        rex_response::setStatus(rex_response::HTTP_OK);
        rex_response::sendJson($rows);
        exit;
    }

    /**
     * Spalten muessen existieren und duerfen keine Zugangsdaten enthalten.
     *
     * @param list<string> $columns
     */
    private function assertColumns(string $table, array $columns): void
    {
        foreach (array_unique($columns) as $column) {
            if (!TableAccess::columnExists($table, $column)) {
                $this->fail(rex_response::HTTP_BAD_REQUEST, 'Unknown column: ' . $column);
            }
            if (TableAccess::isDeniedColumn($column)) {
                $this->fail(rex_response::HTTP_FORBIDDEN, 'Column not allowed: ' . $column);
            }
        }
    }

    /**
     * CONCAT der Label-Felder. "lang:feld" liest mehrsprachige JSON-Felder:
     * ARRAY-Format [{"clang_id":1,"value":"..."}] (YForm lang_text) oder
     * OBJECT-Format {"de":"..."}; Fallback auf den Rohwert.
     *
     * @param list<string> $labelFields bereinigte Feldnamen
     */
    private function labelExpression(array $labelFields, string $rawLabelField, int $clang): string
    {
        $sql = rex_sql::factory();
        $clangId = $clang > 0 ? $clang : rex_clang::getCurrentId();
        $clangObj = rex_clang::get($clangId);
        $langCode = null !== $clangObj ? (string) preg_replace('/[^a-z]/', '', strtolower($clangObj->getCode())) : 'de';
        if ('' === $langCode) {
            $langCode = 'de';
        }

        $langFields = [];
        foreach (explode('|', $rawLabelField) as $field) {
            $field = trim($field);
            if (str_starts_with($field, 'lang:')) {
                $langFields[] = trim(substr($field, 5));
            }
        }

        $expressions = [];
        foreach ($labelFields as $field) {
            $ef = $sql->escapeIdentifier($field);
            if (in_array($field, $langFields, true)) {
                $expressions[] = 'COALESCE('
                    . 'CASE JSON_TYPE(' . $ef . ') '
                    . "WHEN 'ARRAY' THEN JSON_UNQUOTE(JSON_EXTRACT(" . $ef . ', JSON_UNQUOTE(REPLACE(JSON_SEARCH(' . $ef . ", 'one', " . $clangId . ", NULL, '\$[*].clang_id'), 'clang_id', 'value')))) "
                    . "WHEN 'OBJECT' THEN JSON_UNQUOTE(JSON_EXTRACT(" . $ef . ", '\$." . $langCode . "')) "
                    . 'ELSE NULL END, '
                    . $ef
                    . ')';
            } else {
                $expressions[] = $ef;
            }
        }
        if ([] === $expressions) {
            $expressions[] = "''";
        }
        return 'CONCAT_WS(\' \', ' . implode(', ', $expressions) . ') AS label';
    }

    private function fail(string $status, string $message): never
    {
        rex_response::setStatus($status);
        rex_response::sendJson(['error' => $message]);
        exit;
    }
}
