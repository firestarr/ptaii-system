<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPoIdToGoodsReceiptLines extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Tambahkan kolom po_id pada goods_receipt_lines
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('po_id')->nullable()->after('po_line_id');
            $table->foreign('po_id')->references('po_id')->on('purchase_orders');
        });

        // 2. Isi nilai po_id berdasarkan informasi po_line_id
        DB::statement('
            UPDATE goods_receipt_lines grl
            JOIN po_lines pl ON grl.po_line_id = pl.line_id
            SET grl.po_id = pl.po_id
            WHERE grl.po_id IS NULL
        ');

        // 3. Setelah data migrasi, jadikan kolom po_id tidak boleh null
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->unsignedBigInteger('po_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropForeign(['po_id']);
            $table->dropColumn('po_id');
        });
    }
}