<?php

namespace FriendsOfRedaxo\RelationSelect;

use rex_addon;
use rex_config;
use rex_sql_table;
use rex_user;
use rex_yform_manager_table;
use rex_yform_manager_table_perm_edit;
use rex_yform_manager_table_perm_view;

use function in_array;

/**
 * Entscheidet, welche Tabellen und Spalten ein Aufrufer ueber die API lesen darf.
 *
 * Regeln (in dieser Reihenfolge):
 *  1. Tabellen mit Zugangsdaten, Sessions oder Konfiguration sind immer gesperrt.
 *  2. Vom Admin freigegebene Tabellen (Einstellungen) darf jeder Aufrufer lesen,
 *     auch der Frontend-Token.
 *  3. Ohne Backend-User (Token) gibt es keine weiteren Rechte.
 *  4. Admins duerfen alles Uebrige.
 *  5. Backend-User duerfen die Inhaltstabellen des Cores (Artikel, Medien ...)
 *     und YForm-Tabellen, fuer die sie das Tabellenrecht (view/edit) haben.
 *
 * Spalten mit Passwoertern, Tokens oder Session-Daten sind fuer alle gesperrt.
 */
final class TableAccess
{
    /** @var list<string> */
    private const DENIED_TABLES = [
        'rex_user', 'rex_user_session', 'rex_user_passkey', 'rex_user_role',
        'rex_config', 'rex_cronjob', 'rex_yform_history', 'rex_yform_history_field',
    ];

    /** @var list<string> */
    private const CORE_TABLES = [
        'rex_article', 'rex_article_slice', 'rex_media', 'rex_media_category',
        'rex_clang', 'rex_template', 'rex_module', 'rex_metainfo_field',
    ];

    private const DENIED_COLUMN_PATTERN = '~(password|passwd|passkey|token|secret|cookiekey|session|api_?key|activation|reset_?key)~i';

    public static function isValidIdentifier(string $name): bool
    {
        return 1 === preg_match('~^[A-Za-z0-9_]{1,64}$~', $name);
    }

    public static function tableExists(string $table): bool
    {
        return '' !== $table && self::isValidIdentifier($table) && rex_sql_table::get($table)->exists();
    }

    public static function columnExists(string $table, string $column): bool
    {
        return '' !== $table && self::isValidIdentifier($column) && rex_sql_table::get($table)->hasColumn($column);
    }

    public static function isDeniedColumn(string $column): bool
    {
        return 1 === preg_match(self::DENIED_COLUMN_PATTERN, $column);
    }

    public static function isDeniedTable(string $table): bool
    {
        return in_array(strtolower($table), self::DENIED_TABLES, true);
    }

    /**
     * @param rex_user|null $user null = Zugriff per Frontend-Token
     */
    public static function isAllowed(string $table, ?rex_user $user): bool
    {
        $table = strtolower($table);
        if (self::isDeniedTable($table)) {
            return false;
        }
        if (in_array($table, self::allowedTables(), true)) {
            return true;
        }
        if (null === $user) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }
        if (in_array($table, self::CORE_TABLES, true)) {
            return true;
        }
        if (self::isYformTable($table)) {
            return self::hasYformTablePerm($table, $user);
        }
        return false;
    }

    /**
     * Vom Admin freigegebene Tabellen (Einstellungen, komma- oder zeilengetrennt).
     *
     * @return list<string>
     */
    public static function allowedTables(): array
    {
        $raw = (string) rex_config::get('relation_select', 'allowed_tables', '');
        $tables = [];
        foreach (preg_split('~[\s,;|]+~', $raw) ?: [] as $table) {
            $table = strtolower(trim($table));
            if ('' !== $table && self::isValidIdentifier($table) && !self::isDeniedTable($table)) {
                $tables[] = $table;
            }
        }
        return array_values(array_unique($tables));
    }

    public static function isYformTable(string $table): bool
    {
        return rex_addon::get('yform')->isAvailable() && null !== rex_yform_manager_table::get($table);
    }

    private static function hasYformTablePerm(string $table, rex_user $user): bool
    {
        $view = $user->getComplexPerm('yform_manager_table_view');
        $edit = $user->getComplexPerm('yform_manager_table_edit');
        return ($view instanceof rex_yform_manager_table_perm_view && $view->hasPerm($table))
            || ($edit instanceof rex_yform_manager_table_perm_edit && $edit->hasPerm($table));
    }
}
