<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dashboard_layouts', 'name')) {
            Schema::table('dashboard_layouts', function (Blueprint $table) {
                $table->string('name', 80)->default('Home')->after('household_id');
            });
        }

        if (! Schema::hasColumn('dashboard_layouts', 'is_home')) {
            Schema::table('dashboard_layouts', function (Blueprint $table) {
                $table->boolean('is_home')->default(true)->after('name');
            });
        }

        if (! Schema::hasColumn('dashboard_layouts', 'sort_order')) {
            Schema::table('dashboard_layouts', function (Blueprint $table) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('is_home');
            });
        }

        DB::table('dashboard_layouts')->update([
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
        ]);

        // Nuovo unique prima: MySQL riusa il prefisso user_id per la FK; poi si può droppare il vecchio.
        if (! $this->indexExists('dashboard_layouts_user_id_household_id_name_unique')) {
            Schema::table('dashboard_layouts', function (Blueprint $table) {
                $table->unique(['user_id', 'household_id', 'name']);
            });
        }

        $this->dropIndexIfExists('dashboard_layouts_user_id_household_id_unique');

        if (! $this->indexExists('dashboard_layouts_user_id_household_id_is_home_index')) {
            Schema::table('dashboard_layouts', function (Blueprint $table) {
                $table->index(['user_id', 'household_id', 'is_home']);
            });
        }

        if (! $this->indexExists('dashboard_layouts_user_id_household_id_sort_order_index')) {
            Schema::table('dashboard_layouts', function (Blueprint $table) {
                $table->index(['user_id', 'household_id', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('dashboard_layouts_user_id_household_id_is_home_index');
        $this->dropIndexIfExists('dashboard_layouts_user_id_household_id_sort_order_index');

        if (! $this->indexExists('dashboard_layouts_user_id_household_id_unique')) {
            Schema::table('dashboard_layouts', function (Blueprint $table) {
                $table->unique(['user_id', 'household_id']);
            });
        }

        $this->dropIndexIfExists('dashboard_layouts_user_id_household_id_name_unique');

        Schema::table('dashboard_layouts', function (Blueprint $table) {
            if (Schema::hasColumn('dashboard_layouts', 'name')) {
                $table->dropColumn(['name', 'is_home', 'sort_order']);
            }
        });
    }

    private function dropIndexIfExists(string $indexName): void
    {
        if (! $this->indexExists($indexName)) {
            return;
        }

        Schema::table('dashboard_layouts', function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('dashboard_layouts')");

            foreach ($indexes as $index) {
                if (($index->name ?? '') === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = $connection->getDatabaseName();
        $row = $connection->selectOne(
            'select count(*) as aggregate from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?',
            [$database, 'dashboard_layouts', $indexName]
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }
};
