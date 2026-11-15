<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * Postgres connector that forwards libpq "options" to the DSN.
 *
 * Neon requires clients that lack SNI support to identify the compute
 * endpoint with `options=endpoint=<endpoint-id>`. PHP's PDO_PGSQL accepts
 * the option in the DSN, but Laravel's default connector only forwards
 * ssl related options, so we add it here when DB_LIBPQ_OPTIONS is set.
 *
 * The config key is deliberately not `options`, which Laravel reserves for
 * the PDO attribute array.
 */
class NeonPostgresConnector extends PostgresConnector
{
    /**
     * Create a DSN string from a configuration.
     */
    protected function getDsn(array $config): string
    {
        $dsn = parent::getDsn($config);

        $options = $config['libpq_options'] ?? null;

        if (is_array($options)) {
            $options = implode(' ', $options);
        }

        if (is_string($options) && trim($options) !== '') {
            $dsn .= ';options='.trim($options);
        }

        return $dsn;
    }
}
