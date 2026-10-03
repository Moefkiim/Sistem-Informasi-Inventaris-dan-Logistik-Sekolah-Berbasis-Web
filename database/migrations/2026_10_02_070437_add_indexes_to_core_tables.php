<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->index('status');
        });
        Schema::table('incoming_items', function (Blueprint $table) {
            $table->index(['item_id', 'entry_date']);
        });
        Schema::table('outgoing_items', function (Blueprint $table) {
            $table->index(['item_id', 'exit_date']);
        });
        Schema::table('distributions', function (Blueprint $table) {
            $table->index(['item_id', 'distribution_date']);
        });
        Schema::table('items', function (Blueprint $table) {
            $table->index('department');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropIndex(['submissions_status_index']);
        });
        Schema::table('incoming_items', function (Blueprint $table) {
            $table->dropIndex(['incoming_items_item_id_entry_date_index']);
        });
        Schema::table('outgoing_items', function (Blueprint $table) {
            $table->dropIndex(['outgoing_items_item_id_exit_date_index']);
        });
        Schema::table('distributions', function (Blueprint $table) {
            $table->dropIndex(['distributions_item_id_distribution_date_index']);
        });
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['items_department_index']);
        });
    }
};
