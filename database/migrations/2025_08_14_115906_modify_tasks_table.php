<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {

        // First fill NULLs so NOT NULL won't fail
        DB::table('tasks')->whereNull('description')->update(['description' => 'No description']);
        DB::table('tasks')->whereNull('due_date')->update(['due_date' => now()]);

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('is_done');

            $table->enum('status', ['new', 'to do', 'in progress', 'pending', 'done'])->default('new');
            $table->boolean('starred')->default(false);
            // make description required
            $table->text('description')->nullable(false)->change();
            // make due date required
            $table->date('due_date')->nullable(false)->change();

        });
    }

    /**
     * Reverse the migrations. ( in case of mistake : php artisan migrate:rollback )
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('is_done')->default(false);
            $table->dropColumn('status');
            $table->dropColumn('starred');
            $table->text('description')->nullable()->change();
            $table->date('due_date')->nullable()->change();
        });
    }
};
