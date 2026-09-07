<?php

namespace FriendsOfRedaxo\RelationSelect;

use rex_sql;

use function count;
use function in_array;

/**
 * Vereinfachte Filter- und Sortiersyntax (dbw / dbob), gemeinsam fuer API
 * und YForm-Feldtyp.
 *
 * dbw:  "status = 1, name ~ Meier*, deleted = NULL, valid_until > now"
 *       Bedingungen kommasepariert, Werte mit Komma in [[...]]. Operatoren
 *       = != > < >= <= ~ (LIKE, * als Platzhalter). now/today = CURRENT_TIMESTAMP/CURRENT_DATE.
 * dbob: "name, ASC, prio, DESC" -- Feld und Richtung abwechselnd.
 *
 * Feldnamen werden als Bezeichner geprueft und escaped, Werte gebunden.
 */
final class Filter
{
    /** @var list<string> */
    private const OPERATORS = ['!=', '>=', '<=', '=', '>', '<', '~'];

    /**
     * @return array{sql: list<string>, params: list<string>, fields: list<string>}
     */
    public static function parseWhere(string $dbw): array
    {
        $sql = rex_sql::factory();
        $result = ['sql' => [], 'params' => [], 'fields' => []];
        $dbw = trim($dbw);
        if ('' === $dbw) {
            return $result;
        }
        // Kommas innerhalb von [[...]] trennen nicht
        $conditions = preg_split('~,(?![^\[]*\]\])~', $dbw) ?: [];
        foreach ($conditions as $condition) {
            $parsed = self::parseCondition(trim($condition));
            if (null === $parsed) {
                continue;
            }
            [$field, $operator, $value] = $parsed;
            $result['fields'][] = $field;
            $escaped = $sql->escapeIdentifier($field);

            if ('NULL' === strtoupper($value)) {
                $result['sql'][] = $escaped . ('!=' === $operator ? ' IS NOT NULL' : ' IS NULL');
                continue;
            }
            $lower = strtolower($value);
            if ('now' === $lower || 'today' === $lower) {
                $result['sql'][] = $escaped . ' ' . $operator . ' ' . ('now' === $lower ? 'CURRENT_TIMESTAMP' : 'CURRENT_DATE');
                continue;
            }
            if ('~' === $operator) {
                $like = str_replace('*', '%', $sql->escapeLikeWildcards($value));
                if (!str_contains($like, '%')) {
                    $like = '%' . $like . '%';
                }
                $result['sql'][] = $escaped . ' LIKE ?';
                $result['params'][] = $like;
                continue;
            }
            $result['sql'][] = $escaped . ' ' . $operator . ' ?';
            $result['params'][] = $value;
        }
        return $result;
    }

    /**
     * @return array{0: string, 1: string, 2: string}|null [Feld, Operator, Wert]
     */
    private static function parseCondition(string $condition): ?array
    {
        if ('' === $condition) {
            return null;
        }
        foreach (self::OPERATORS as $operator) {
            $pos = strpos($condition, $operator);
            if (false === $pos) {
                continue;
            }
            $field = trim(substr($condition, 0, $pos));
            $value = trim(substr($condition, $pos + strlen($operator)));
            if (!TableAccess::isValidIdentifier($field)) {
                return null;
            }
            if (str_starts_with($value, '[[') && str_ends_with($value, ']]')) {
                $value = substr($value, 2, -2);
            }
            return [$field, $operator, $value];
        }
        return null;
    }

    /**
     * @return array{sql: list<string>, fields: list<string>}
     */
    public static function parseOrder(string $dbob): array
    {
        $sql = rex_sql::factory();
        $result = ['sql' => [], 'fields' => []];
        $parts = array_values(array_filter(array_map('trim', explode(',', $dbob)), static fn (string $p): bool => '' !== $p));
        $i = 0;
        while ($i < count($parts)) {
            $field = $parts[$i];
            $next = strtoupper($parts[$i + 1] ?? '');
            if (in_array($next, ['ASC', 'DESC'], true)) {
                $direction = $next;
                $i += 2;
            } else {
                $direction = 'ASC';
                ++$i;
            }
            if (!TableAccess::isValidIdentifier($field)) {
                continue;
            }
            $result['fields'][] = $field;
            $result['sql'][] = $sql->escapeIdentifier($field) . ' ' . $direction;
        }
        return $result;
    }

    /**
     * Feldliste "a|b|lang:c" -> ['a', 'b', 'c'] (Praefixe wie lang:, badge:, color: entfernt, "(id)" ignoriert).
     *
     * @return list<string>
     */
    public static function fieldNames(string $list): array
    {
        $fields = [];
        foreach (explode('|', $list) as $field) {
            $field = trim($field);
            if ('' === $field || '(id)' === $field) {
                continue;
            }
            $colon = strpos($field, ':');
            if (false !== $colon) {
                $field = trim(substr($field, $colon + 1));
            }
            if ('' !== $field) {
                $fields[] = $field;
            }
        }
        return $fields;
    }
}
