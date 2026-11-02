<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;

/**
 * Laravel casts bool bindings to int (1/0). With PDO::ATTR_EMULATE_PREPARES on
 * (required for Supabase's transaction pooler), PDO inlines that int into the
 * SQL and Postgres rejects it for boolean columns ("column is of type boolean
 * but expression is of type integer"). Bind booleans as 'true'/'false' string
 * literals instead, which Postgres coerces to boolean in both native and
 * emulated prepare modes.
 */
class PostgresConnection extends BasePostgresConnection
{
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return parent::prepareBindings($bindings);
    }
}
