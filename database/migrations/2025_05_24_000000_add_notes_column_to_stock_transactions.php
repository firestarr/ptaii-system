<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNotesColumnToStockTransactions extends Migration
{
    public function up()
    {
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->string('notes')->nullable()->after('state');
        });
    }

    public function down()
    {
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
}
