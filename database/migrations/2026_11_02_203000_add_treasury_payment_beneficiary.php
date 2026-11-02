<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_procedure_settings', function (Blueprint $table): void {
            $table->string('treasury_payment_beneficiary', 160)
                ->nullable()
                ->after('treasury_payment_iban');
        });
    }

    public function down(): void
    {
        Schema::table('member_procedure_settings', function (Blueprint $table): void {
            $table->dropColumn('treasury_payment_beneficiary');
        });
    }
};
