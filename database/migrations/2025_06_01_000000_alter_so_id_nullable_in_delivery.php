<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterSoIdNullableInDelivery extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('Delivery', function (Blueprint $table) {
            $table->unsignedBigInteger('so_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('Delivery', function (Blueprint $table) {
            $table->unsignedBigInteger('so_id')->nullable(false)->change();
        });
    }
}
