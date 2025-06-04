<?php
// database/migrations/2024_01_15_100000_add_consolidated_delivery_support.php

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
        Schema::table('Delivery', function (Blueprint $table) {
            // Add consolidated delivery support columns
            $table->boolean('is_consolidated')->default(false)->after('status');
            $table->json('consolidated_so_ids')->nullable()->after('is_consolidated');
            $table->text('notes')->nullable()->after('consolidated_so_ids');
            
            // Add timestamps if not exists (for better tracking)
            if (!Schema::hasColumn('Delivery', 'created_at')) {
                $table->timestamps();
            }
            
            // Add indexes for performance
            $table->index('is_consolidated', 'idx_delivery_consolidated');
            $table->index(['customer_id', 'is_consolidated'], 'idx_delivery_customer_consolidated');
            $table->index(['delivery_date', 'is_consolidated'], 'idx_delivery_date_consolidated');
            $table->index('status', 'idx_delivery_status');
        });

        // Update existing deliveries to mark them as non-consolidated
        DB::statement('UPDATE "Delivery" SET is_consolidated = FALSE WHERE is_consolidated IS NULL');


        
        // Add constraint to ensure data integrity
        DB::statement('
            ALTER TABLE "Delivery"
            ADD CONSTRAINT chk_consolidated_delivery_integrity 
            CHECK (
                (is_consolidated = FALSE AND so_id IS NOT NULL AND consolidated_so_ids IS NULL) OR        
                (is_consolidated = TRUE AND so_id IS NULL AND consolidated_so_ids IS NOT NULL)
            )
        ');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('Delivery', function (Blueprint $table) {
            // Drop constraint first
            $table->dropCheckConstraint('chk_consolidated_delivery_integrity');
            
            // Drop indexes
            $table->dropIndex('idx_delivery_consolidated');
            $table->dropIndex('idx_delivery_customer_consolidated');
            $table->dropIndex('idx_delivery_date_consolidated');
            $table->dropIndex('idx_delivery_status');
            
            // Drop columns
            $table->dropColumn(['is_consolidated', 'consolidated_so_ids', 'notes']);
        });
    }
};