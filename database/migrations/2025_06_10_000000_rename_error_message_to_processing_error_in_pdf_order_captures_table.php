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
            if (Schema::hasColumn('pdf_order_captures', 'error_message')) {
                $table->renameColumn('error_message', 'processing_error');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_order_captures', function (Blueprint $table) {
            if (Schema::hasColumn('pdf_order_captures', 'processing_error')) {
                $table->renameColumn('processing_error', 'error_message');
            }
        });
    }
};
