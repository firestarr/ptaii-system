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
            if (!Schema::hasColumn('pdf_order_captures', 'processed_by')) {
                $table->foreignId('processed_by')->nullable()->after('created_so_id')->constrained('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_order_captures', function (Blueprint $table) {
            if (Schema::hasColumn('pdf_order_captures', 'processed_by')) {
                $table->dropForeign(['processed_by']);
                $table->dropColumn('processed_by');
            }
        });
    }
};
