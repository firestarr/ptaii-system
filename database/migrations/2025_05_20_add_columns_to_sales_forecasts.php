<?php
// Migrasi: 2025_05_20_add_version_columns_to_sales_forecasts.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('SalesForecast', function (Blueprint $table) {
            // Tambahkan kolom baru
            $table->date('forecast_issue_date')->nullable()->comment('Tanggal terbit forecast dari customer');
            $table->date('submission_date')->nullable()->comment('Tanggal forecast diupload ke sistem');
            $table->boolean('is_current_version')->default(true);
            $table->bigInteger('previous_version_id')->nullable();
            
            // Tambahkan indeks untuk kolom baru
            $table->index('forecast_issue_date');
            $table->index('is_current_version');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('SalesForecast', function (Blueprint $table) {
            // Hapus kolom yang ditambahkan
            $table->dropColumn('forecast_issue_date');
            $table->dropColumn('submission_date');
            $table->dropColumn('is_current_version');
            $table->dropColumn('previous_version_id');
        });
    }
};