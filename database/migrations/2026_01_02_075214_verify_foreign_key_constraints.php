<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Verify and fix foreign key constraints with correct onDelete actions
     */
    public function up(): void
    {
        // Verify and fix invoices table foreign keys
        if (Schema::hasTable('invoices')) {
            // Check if organisation_id foreign key exists and has correct onDelete
            $orgFk = DB::select("
                SELECT rc.CONSTRAINT_NAME, rc.DELETE_RULE 
                FROM information_schema.REFERENTIAL_CONSTRAINTS rc
                INNER JOIN information_schema.KEY_COLUMN_USAGE kcu 
                    ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME 
                    AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                WHERE rc.CONSTRAINT_SCHEMA = DATABASE() 
                AND kcu.TABLE_NAME = 'invoices' 
                AND kcu.COLUMN_NAME = 'organisation_id' 
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            // If foreign key exists but has wrong onDelete, drop and recreate
            if (count($orgFk) > 0 && $orgFk[0]->DELETE_RULE !== 'CASCADE') {
                Schema::table('invoices', function (Blueprint $table) use ($orgFk) {
                    try {
                        $table->dropForeign([$orgFk[0]->CONSTRAINT_NAME]);
                    } catch (\Exception $e) {
                        // Foreign key might not exist, continue
                    }
                });
            }
            
            // Add organisation_id foreign key if it doesn't exist or was dropped
            if (count($orgFk) === 0 || $orgFk[0]->DELETE_RULE !== 'CASCADE') {
                Schema::table('invoices', function (Blueprint $table) {
                    try {
                        $table->foreign('organisation_id')
                            ->references('id')
                            ->on('organisations')
                            ->onDelete('cascade');
                    } catch (\Exception $e) {
                        // Foreign key might already exist, continue
                    }
                });
            }
            
            // Check subscription_id foreign key
            $subFk = DB::select("
                SELECT rc.CONSTRAINT_NAME, rc.DELETE_RULE 
                FROM information_schema.REFERENTIAL_CONSTRAINTS rc
                INNER JOIN information_schema.KEY_COLUMN_USAGE kcu 
                    ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME 
                    AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                WHERE rc.CONSTRAINT_SCHEMA = DATABASE() 
                AND kcu.TABLE_NAME = 'invoices' 
                AND kcu.COLUMN_NAME = 'subscription_id' 
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            // If foreign key exists but has wrong onDelete, drop and recreate
            if (count($subFk) > 0 && $subFk[0]->DELETE_RULE !== 'SET NULL') {
                Schema::table('invoices', function (Blueprint $table) use ($subFk) {
                    try {
                        $table->dropForeign([$subFk[0]->CONSTRAINT_NAME]);
                    } catch (\Exception $e) {
                        // Foreign key might not exist, continue
                    }
                });
            }
            
            // Add subscription_id foreign key with set null (subscription can be deleted, invoice remains)
            if (count($subFk) === 0 || $subFk[0]->DELETE_RULE !== 'SET NULL') {
                Schema::table('invoices', function (Blueprint $table) {
                    try {
                        $table->foreign('subscription_id')
                            ->references('id')
                            ->on('subscription_records')
                            ->onDelete('set null');
                    } catch (\Exception $e) {
                        // Foreign key might already exist, continue
                    }
                });
            }
        }
        
        // Verify subscription_records table foreign keys
        if (Schema::hasTable('subscription_records')) {
            $subOrgFk = DB::select("
                SELECT rc.CONSTRAINT_NAME, rc.DELETE_RULE 
                FROM information_schema.REFERENTIAL_CONSTRAINTS rc
                INNER JOIN information_schema.KEY_COLUMN_USAGE kcu 
                    ON rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME 
                    AND rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA
                WHERE rc.CONSTRAINT_SCHEMA = DATABASE() 
                AND kcu.TABLE_NAME = 'subscription_records' 
                AND kcu.COLUMN_NAME = 'organisation_id' 
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ");
            
            // Add organisation_id foreign key if it doesn't exist
            if (count($subOrgFk) === 0) {
                Schema::table('subscription_records', function (Blueprint $table) {
                    try {
                        $table->foreign('organisation_id')
                            ->references('id')
                            ->on('organisations')
                            ->onDelete('cascade');
                    } catch (\Exception $e) {
                        // Foreign key might already exist, continue
                    }
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't drop foreign keys in down() to preserve data integrity
    }
};
