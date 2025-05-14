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
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            // Add columns for invoice tracking
            $table->boolean('is_invoiced')->default(false)->after('batch_number');
            $table->unsignedBigInteger('invoice_line_id')->nullable()->after('is_invoiced');
            
            // Add column for purchase order ID
            $table->unsignedBigInteger('po_id')->nullable()->after('po_line_id');
            
            // Add foreign key constraints
            $table->foreign('invoice_line_id')
                ->references('line_id')
                ->on('vendor_invoice_lines')
                ->onDelete('set null');
                
            $table->foreign('po_id')
                ->references('po_id')
                ->on('purchase_orders')
                ->onDelete('set null');
        });
        
        // Use PostgreSQL-specific syntax to populate po_id from po_lines
        DB::statement('
            UPDATE goods_receipt_lines grl
            SET po_id = pl.po_id
            FROM po_lines pl
            WHERE grl.po_line_id = pl.line_id
            AND grl.po_id IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['invoice_line_id']);
            $table->dropForeign(['po_id']);
            
            // Then drop columns
            $table->dropColumn('invoice_line_id');
            $table->dropColumn('is_invoiced');
            $table->dropColumn('po_id');
        });
    }
};