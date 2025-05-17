<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemovePoIdFromGoodsReceipts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Hapus foreign key dan kolom po_id dari goods_receipts
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropForeign(['po_id']);
            $table->dropColumn('po_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Untuk rollback, tambahkan kembali kolom po_id dan isi dengan nilai dari PO pertama
        // yang terkait dengan goods receipt (jika hanya ada satu PO)
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->unsignedBigInteger('po_id')->nullable()->after('receipt_date');
            $table->foreign('po_id')->references('po_id')->on('purchase_orders');
        });
        
        // Update nilai po_id untuk goods receipt yang hanya memiliki satu po_id
        DB::statement('
            UPDATE goods_receipts gr
            JOIN (
                SELECT receipt_id, po_id
                FROM goods_receipt_lines
                GROUP BY receipt_id
                HAVING COUNT(DISTINCT po_id) = 1
            ) AS unique_po ON gr.receipt_id = unique_po.receipt_id
            SET gr.po_id = unique_po.po_id
        ');
    }

    /**
     * Get list of foreign keys for a table.
     *
     * @param string $table
     * @return array
     */
    private function listTableForeignKeys($table)
    {
        // This method is no longer needed after simplifying the up method
        return [];
    }
}