<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

/**
 * Ripara installazioni dove la migration Pulse è stata registrata senza creare
 * le tabelle (es. PULSE_ENABLED=false) oppure il file SQLite era assente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = Config::get('pulse.storage.database.connection', 'pulse');
        $database = Config::get('database.connections.'.$connection.'.database');

        if (is_string($database) && $database !== '' && ! str_contains($database, ':memory:')) {
            $dir = dirname($database);
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            if (! file_exists($database)) {
                touch($database);
            }
        }

        $schema = Schema::connection($connection);
        if ($schema->hasTable('pulse_values')) {
            return;
        }

        $driver = $schema->getConnection()->getDriverName();

        $schema->create('pulse_values', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->mediumText('key');
            match ($driver) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                default => $table->string('key_hash'),
            };
            $table->mediumText('value');

            $table->index('timestamp');
            $table->index('type');
            $table->unique(['type', 'key_hash']);
        });

        $schema->create('pulse_entries', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->mediumText('key');
            match ($driver) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                default => $table->string('key_hash'),
            };
            $table->bigInteger('value')->nullable();

            $table->index('timestamp');
            $table->index('type');
            $table->index('key_hash');
            $table->index(['timestamp', 'type', 'key_hash', 'value']);
        });

        $schema->create('pulse_aggregates', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->unsignedInteger('bucket');
            $table->unsignedMediumInteger('period');
            $table->string('type');
            $table->mediumText('key');
            match ($driver) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                default => $table->string('key_hash'),
            };
            $table->string('aggregate');
            $table->decimal('value', 20, 2);
            $table->unsignedInteger('count')->nullable();

            $table->unique(['bucket', 'period', 'type', 'aggregate', 'key_hash']);
            $table->index(['period', 'bucket']);
            $table->index('type');
            $table->index(['period', 'type', 'aggregate', 'bucket']);
        });
    }

    public function down(): void
    {
        // Non droppare: la migration originale gestisce il down dello schema Pulse.
    }
};
