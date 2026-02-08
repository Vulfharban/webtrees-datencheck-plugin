<?php

namespace Wolfrum\Datencheck\Services;

use Fisharebest\Webtrees\DB;
use Illuminate\Database\Schema\Blueprint;

class SchemaService
{
    /**
     * Create/Update database table for ignored errors.
     * Checks if table exists, if not creates it.
     */
    public static function updateSchema(): void
    {
        $schema = DB::schema();
        $table_name = 'datencheck_ignored';

        if (!$schema->hasTable($table_name)) {
            $schema->create($table_name, function (Blueprint $table) {
                $table->integer('tree_id');
                $table->string('xref', 50);
                $table->string('error_code', 100); // e.g. 'MOTHER_TOO_YOUNG'
                $table->string('user_name', 100)->nullable();
                $table->text('comment')->nullable(); // Optional reason
                $table->timestamp('created_at')->useCurrent();

                // Composite Primary Key: A specific error for a specific person in a specific tree
                $table->primary(['tree_id', 'xref', 'error_code']);
                
                // Index for faster lookups during validation
                $table->index(['tree_id', 'xref']);

                // Foreign Key: Link to gedcom table to ensure cleanup if tree is deleted
                $table->foreign('tree_id', 'datencheck_ignored_tree_id_foreign')
                      ->references('g_id')->on('gedcom')
                      ->onDelete('cascade');
            });
        } else {
            // Attempt to add foreign key if table already exists (e.g. from previous version)
            try {
                $schema->table($table_name, function (Blueprint $table) {
                    $table->foreign('tree_id', 'datencheck_ignored_tree_id_foreign')
                          ->references('g_id')->on('gedcom')
                          ->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // Foreign key likely already exists, ignore
            }
        }
    }
}
