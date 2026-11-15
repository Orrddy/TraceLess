<?php

namespace Tests\Unit;

use App\Database\NeonPostgresConnector;
use PHPUnit\Framework\TestCase;

class NeonPostgresConnectorTest extends TestCase
{
    public function test_dsn_forwards_libpq_options(): void
    {
        $dsn = $this->connector()->dsn([
            'driver' => 'pgsql',
            'host' => 'ep-example.c-16.us-east-1.aws.neon.tech',
            'port' => 5432,
            'database' => 'neondb',
            'sslmode' => 'require',
            'libpq_options' => 'endpoint=ep-example',
        ]);

        $this->assertStringContainsString('host=ep-example.c-16.us-east-1.aws.neon.tech', $dsn);
        $this->assertStringContainsString('dbname=\'neondb\'', $dsn);
        $this->assertStringContainsString('sslmode=require', $dsn);
        $this->assertStringContainsString('options=endpoint=ep-example', $dsn);
    }

    public function test_dsn_omits_options_when_not_configured(): void
    {
        $dsn = $this->connector()->dsn([
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'database' => 'traceless',
        ]);

        $this->assertStringNotContainsString('options=', $dsn);
    }

    private function connector(): NeonPostgresConnector
    {
        return new class extends NeonPostgresConnector
        {
            public function dsn(array $config): string
            {
                return $this->getDsn($config);
            }
        };
    }
}
