<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contact_submissions')) {
            return;
        }

        Schema::table('contact_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('contact_submissions', 'full_name')) {
                $table->string('full_name', 160)->nullable();
            }

            if (! Schema::hasColumn('contact_submissions', 'birth_date')) {
                $table->date('birth_date')->nullable();
            }

            if (! Schema::hasColumn('contact_submissions', 'residence')) {
                $table->string('residence', 160)->nullable();
            }

            if (! Schema::hasColumn('contact_submissions', 'phone_whatsapp')) {
                $table->string('phone_whatsapp', 40)->nullable();
            }

            if (! Schema::hasColumn('contact_submissions', 'how_heard_us')) {
                $table->text('how_heard_us')->nullable();
            }

            if (! Schema::hasColumn('contact_submissions', 'experience_summary')) {
                $table->text('experience_summary')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contact_submissions')) {
            return;
        }

        $columns = array_values(array_filter([
            'full_name',
            'birth_date',
            'residence',
            'phone_whatsapp',
            'how_heard_us',
            'experience_summary',
        ], fn (string $column): bool => Schema::hasColumn('contact_submissions', $column)));

        if ($columns === []) {
            return;
        }

        Schema::table('contact_submissions', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
