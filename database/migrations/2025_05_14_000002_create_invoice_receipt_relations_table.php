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
        Schema::create('invoice_receipt_relations', function (Blueprint $table) {
            $table->id('relation_id');
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('receipt_id');
            $table->timestamps();
            
            // Add foreign key constraints
            $table->foreign('invoice_id')
                ->references('invoice_id')
                ->on('vendor_invoices')
                ->onDelete('cascade');
                
            $table->foreign('receipt_id')
                ->references('receipt_id')
                ->on('goods_receipts')
                ->onDelete('cascade');
            
            // Add unique constraint to prevent duplicates
            $table->unique(['invoice_id', 'receipt_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_receipt_relations');
    }
};