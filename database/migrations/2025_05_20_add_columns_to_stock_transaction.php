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
        Schema::table('stock_transactions', function (Blueprint $table) {
            // Add destination warehouse for transfers (like Odoo stock.move)
            $table->unsignedBigInteger('dest_warehouse_id')->nullable()->after('warehouse_id');
            $table->foreign('dest_warehouse_id')->references('warehouse_id')->on('warehouses')->onDelete('set null');
            
            // Add state field (like Odoo)
            $table->string('state', 20)->default('draft')->after('quantity'); // draft, confirmed, done, cancelled
            
            // Add move_type field for better categorization
            $table->string('move_type', 50)->default('internal')->after('transaction_type'); // internal, in, out
            
            // Add origin reference for tracking source document
            $table->string('origin', 100)->nullable()->after('reference_number');
        });
        
        // Update existing data to set state as 'done' for completed transactions
        DB::statement("UPDATE stock_transactions SET state = 'done', move_type = CASE 
            WHEN transaction_type = 'receive' THEN 'in'
            WHEN transaction_type = 'issue' THEN 'out' 
            WHEN transaction_type = 'transfer' THEN 'internal'
            ELSE 'internal'
        END");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->dropForeign(['dest_warehouse_id']);
            $table->dropColumn(['dest_warehouse_id', 'state', 'move_type', 'origin']);
        });
    }
};