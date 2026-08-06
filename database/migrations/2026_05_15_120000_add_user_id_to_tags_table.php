<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL usa l'indice unico (household_id, name) per il vincolo FK su household_id:
        // serve un indice dedicato su household_id prima di rimuovere quello unico.
        if (! $this->indexExists('tags', 'tags_household_id_index')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->index('household_id', 'tags_household_id_index');
            });
        }

        if ($this->indexExists('tags', 'tags_household_name_unique')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropUnique('tags_household_name_unique');
            });
        }

        if (! Schema::hasColumn('tags', 'user_id')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('household_id')->constrained('users')->cascadeOnDelete();
            });
        }

        $this->backfillTagOwners();

        if (! $this->indexExists('tags', 'tags_household_user_name_unique')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->unique(['household_id', 'user_id', 'name'], 'tags_household_user_name_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->indexExists('tags', 'tags_household_user_name_unique')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropUnique('tags_household_user_name_unique');
            });
        }

        if (Schema::hasColumn('tags', 'user_id')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (! $this->indexExists('tags', 'tags_household_name_unique')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->unique(['household_id', 'name'], 'tags_household_name_unique');
            });
        }

        if ($this->indexExists('tags', 'tags_household_id_index')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropIndex('tags_household_id_index');
            });
        }
    }

    private function backfillTagOwners(): void
    {
        $tags = DB::table('tags')->whereNull('deleted_at')->whereNull('user_id')->get(['id', 'household_id']);

        foreach ($tags as $tag) {
            $ownerUserId = DB::table('transaction_tag')
                ->join('transactions', 'transactions.id', '=', 'transaction_tag.transaction_id')
                ->where('transaction_tag.tag_id', $tag->id)
                ->whereNull('transactions.deleted_at')
                ->select('transactions.user_id', DB::raw('COUNT(*) as usage_count'))
                ->groupBy('transactions.user_id')
                ->orderByDesc('usage_count')
                ->value('transactions.user_id');

            if (! $ownerUserId) {
                $ownerUserId = DB::table('households')
                    ->where('id', $tag->household_id)
                    ->value('owner_user_id');
            }

            if ($ownerUserId) {
                DB::table('tags')->where('id', $tag->id)->update(['user_id' => $ownerUserId]);
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            return collect($indexes)->contains(fn ($index) => $index->name === $indexName);
        }

        $database = Schema::getConnection()->getDatabaseName();
        $result = DB::select(
            'SELECT COUNT(*) AS count FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $indexName]
        );

        return (int) ($result[0]->count ?? 0) > 0;
    }
};
