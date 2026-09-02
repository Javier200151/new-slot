<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pollColumns = [
            'selection_mode' => fn (Blueprint $table) => $table->string('selection_mode', 16)->default('single')->after('is_published'),
            'min_choices' => fn (Blueprint $table) => $table->unsignedTinyInteger('min_choices')->default(1)->after('selection_mode'),
            'max_choices' => fn (Blueprint $table) => $table->unsignedTinyInteger('max_choices')->nullable()->after('min_choices'),
            'allow_vote_change' => fn (Blueprint $table) => $table->boolean('allow_vote_change')->default(true)->after('max_choices'),
            'is_anonymous' => fn (Blueprint $table) => $table->boolean('is_anonymous')->default(false)->after('allow_vote_change'),
            'results_visibility' => fn (Blueprint $table) => $table->string('results_visibility', 24)->default('always')->after('is_anonymous'),
            'show_voter_names' => fn (Blueprint $table) => $table->boolean('show_voter_names')->default(false)->after('results_visibility'),
            'show_participation' => fn (Blueprint $table) => $table->boolean('show_participation')->default(true)->after('show_voter_names'),
            'allow_abstain' => fn (Blueprint $table) => $table->boolean('allow_abstain')->default(false)->after('show_participation'),
            'randomize_options' => fn (Blueprint $table) => $table->boolean('randomize_options')->default(false)->after('allow_abstain'),
            'quorum_percent' => fn (Blueprint $table) => $table->unsignedTinyInteger('quorum_percent')->nullable()->after('randomize_options'),
        ];

        foreach ($pollColumns as $column => $definition) {
            if (Schema::hasColumn('community_polls', $column)) {
                continue;
            }

            Schema::table('community_polls', function (Blueprint $table) use ($definition): void {
                $definition($table);
            });
        }

        // community_poll_id necesita seguir indexado para su FK. El UNIQUE
        // antiguo era el índice que MySQL estaba utilizando para esa FK, por
        // eso debemos crear primero un índice sustituto y después quitarlo.
        if (! $this->indexExists(
            'community_poll_votes',
            'community_poll_votes_poll_user_index'
        )) {
            Schema::table('community_poll_votes', function (Blueprint $table): void {
                $table->index(
                    ['community_poll_id', 'user_id'],
                    'community_poll_votes_poll_user_index'
                );
            });
        }

        if ($this->indexExists(
            'community_poll_votes',
            'community_poll_votes_community_poll_id_user_id_unique'
        )) {
            Schema::table('community_poll_votes', function (Blueprint $table): void {
                $table->dropUnique(
                    'community_poll_votes_community_poll_id_user_id_unique'
                );
            });
        }

        if (! Schema::hasColumn('community_poll_votes', 'is_abstain')) {
            Schema::table('community_poll_votes', function (Blueprint $table): void {
                $table->boolean('is_abstain')
                    ->default(false)
                    ->after('community_poll_option_id');
            });
        }

        // La abstención no apunta a una opción concreta, por lo que esta FK
        // debe aceptar NULL. Es seguro repetir este ALTER si la migración quedó
        // aplicada a medias en un intento anterior.
        DB::statement(
            'ALTER TABLE community_poll_votes '
            .'MODIFY community_poll_option_id BIGINT UNSIGNED NULL'
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('community_poll_votes', 'is_abstain')) {
            DB::table('community_poll_votes')
                ->where('is_abstain', true)
                ->delete();
        }

        $duplicates = DB::table('community_poll_votes')
            ->select(
                'community_poll_id',
                'user_id',
                DB::raw('MIN(id) as keep_id')
            )
            ->groupBy('community_poll_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('community_poll_votes')
                ->where('community_poll_id', $duplicate->community_poll_id)
                ->where('user_id', $duplicate->user_id)
                ->where('id', '<>', $duplicate->keep_id)
                ->delete();
        }

        DB::statement(
            'ALTER TABLE community_poll_votes '
            .'MODIFY community_poll_option_id BIGINT UNSIGNED NOT NULL'
        );

        // Restauramos primero el UNIQUE, que vuelve a servir de índice para la
        // FK de community_poll_id. Solo después retiramos el índice sustituto.
        if (! $this->indexExists(
            'community_poll_votes',
            'community_poll_votes_community_poll_id_user_id_unique'
        )) {
            Schema::table('community_poll_votes', function (Blueprint $table): void {
                $table->unique(['community_poll_id', 'user_id']);
            });
        }

        if ($this->indexExists(
            'community_poll_votes',
            'community_poll_votes_poll_user_index'
        )) {
            Schema::table('community_poll_votes', function (Blueprint $table): void {
                $table->dropIndex('community_poll_votes_poll_user_index');
            });
        }

        if (Schema::hasColumn('community_poll_votes', 'is_abstain')) {
            Schema::table('community_poll_votes', function (Blueprint $table): void {
                $table->dropColumn('is_abstain');
            });
        }

        $columns = [
            'selection_mode',
            'min_choices',
            'max_choices',
            'allow_vote_change',
            'is_anonymous',
            'results_visibility',
            'show_voter_names',
            'show_participation',
            'allow_abstain',
            'randomize_options',
            'quorum_percent',
        ];

        $existingColumns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn(
                'community_polls',
                $column
            )
        ));

        if ($existingColumns !== []) {
            Schema::table('community_polls', function (Blueprint $table) use ($existingColumns): void {
                $table->dropColumn($existingColumns);
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
