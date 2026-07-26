<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE operations
            SET description = JSON_OBJECT('content', description)
            WHERE description IS NOT NULL
                AND JSON_VALID(description) = 0
        SQL);

        DB::statement(<<<'SQL'
            UPDATE operations
            SET radio = JSON_OBJECT('content', radio)
            WHERE radio IS NOT NULL
                AND JSON_VALID(radio) = 0
        SQL);

        DB::statement(<<<'SQL'
            UPDATE operations
            SET addons = JSON_OBJECT('content', addons)
            WHERE addons IS NOT NULL
                AND JSON_VALID(addons) = 0
        SQL);

        DB::statement('ALTER TABLE operations MODIFY description JSON NULL');
        DB::statement('ALTER TABLE operations MODIFY radio JSON NULL');
        DB::statement('ALTER TABLE operations MODIFY addons JSON NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE operations MODIFY description LONGTEXT NULL');
        DB::statement('ALTER TABLE operations MODIFY radio TEXT NULL');
        DB::statement('ALTER TABLE operations MODIFY addons TEXT NULL');

        DB::statement(<<<'SQL'
            UPDATE operations
            SET description = JSON_UNQUOTE(JSON_EXTRACT(description, '$.content'))
            WHERE description IS NOT NULL
                AND JSON_VALID(description)
                AND JSON_EXTRACT(description, '$.content') IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE operations
            SET radio = JSON_UNQUOTE(JSON_EXTRACT(radio, '$.content'))
            WHERE radio IS NOT NULL
                AND JSON_VALID(radio)
                AND JSON_EXTRACT(radio, '$.content') IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            UPDATE operations
            SET addons = JSON_UNQUOTE(JSON_EXTRACT(addons, '$.content'))
            WHERE addons IS NOT NULL
                AND JSON_VALID(addons)
                AND JSON_EXTRACT(addons, '$.content') IS NOT NULL
        SQL);
    }
};
