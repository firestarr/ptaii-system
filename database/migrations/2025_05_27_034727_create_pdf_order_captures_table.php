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
        Schema::create('pdf_order_captures', function (Blueprint $table) {
            $table->id();
            
            // File information
            $table->string('filename');
            $table->string('file_path');
            $table->bigInteger('file_size')->nullable();
            $table->string('file_hash', 64)->nullable()->index();
            
            // Processing status and tracking
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
            
            // User who uploaded the file
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Reference to created sales order
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->foreign('sales_order_id')->references('so_id')->on('SalesOrder')->onDelete('set null');
            
            // Extracted data from AI processing
            $table->json('extracted_data')->nullable();
            
            // Processing configuration options
            $table->json('processing_options')->nullable();
            
            // AI confidence score (0-100)
            $table->decimal('confidence_score', 5, 2)->nullable();
            
            // Error handling
            $table->text('error_message')->nullable();
            
            // Processing timestamps and performance
            $table->timestamp('processed_at')->nullable();
            $table->integer('processing_duration')->nullable(); // in seconds
            
            // Standard timestamps
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['sales_order_id']);
            $table->index(['created_at']);
        });
        
        // Add comments to the table for documentation (PostgreSQL compatible)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON TABLE pdf_order_captures IS 'Tracks PDF order capture processing history and results'");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE pdf_order_captures COMMENT = 'Tracks PDF order capture processing history and results'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pdf_order_captures');
    }
};