<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pdf_order_captures', function (Blueprint $table) {
            $table->json('ai_raw_response')->nullable()->after('extracted_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_order_captures', function (Blueprint $table) {
            $table->dropColumn('ai_raw_response');
        });
    }
};
