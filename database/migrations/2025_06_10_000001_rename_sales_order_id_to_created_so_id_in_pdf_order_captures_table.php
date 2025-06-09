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
            if (Schema::hasColumn('pdf_order_captures', 'sales_order_id')) {
                $table->renameColumn('sales_order_id', 'created_so_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pdf_order_captures', function (Blueprint $table) {
            if (Schema::hasColumn('pdf_order_captures', 'created_so_id')) {
                $table->renameColumn('created_so_id', 'sales_order_id');
            }
        });
    }
};
