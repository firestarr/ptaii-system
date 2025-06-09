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
            $table->dropColumn('status');
        });

        Schema::table('pdf_order_captures', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'processing',
                'data_extracted',
                'validating',
                'creating_order',
                'so_created',
                'completed',
                'failed',
                'cancelled'
            ])->default('pending')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_order_captures', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('pdf_order_captures', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'processing',
                'data_extracted',
                'validating',
                'creating_order',
                'completed',
                'failed',
                'cancelled'
            ])->default('pending')->index();
        });
    }
};
