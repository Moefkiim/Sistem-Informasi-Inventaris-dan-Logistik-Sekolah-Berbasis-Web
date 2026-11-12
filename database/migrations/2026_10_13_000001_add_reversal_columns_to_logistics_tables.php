<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incoming_items', function (Blueprint $table) {
            if (! Schema::hasColumn('incoming_items', 'reverses_id')) {
                $table->foreignId('reverses_id')->nullable()->constrained('incoming_items')->nullOnDelete();
            }
            if (! Schema::hasColumn('incoming_items', 'reversal_reason')) {
                $table->text('reversal_reason')->nullable();
            }
            if (! Schema::hasColumn('incoming_items', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable();
            }
        });

        Schema::table('outgoing_items', function (Blueprint $table) {
            if (! Schema::hasColumn('outgoing_items', 'reverses_id')) {
                $table->foreignId('reverses_id')->nullable()->constrained('outgoing_items')->nullOnDelete();
            }
            if (! Schema::hasColumn('outgoing_items', 'reversal_reason')) {
                $table->text('reversal_reason')->nullable();
            }
            if (! Schema::hasColumn('outgoing_items', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('incoming_items', function (Blueprint $table) {
            if (Schema::hasColumn('incoming_items', 'reverses_id')) {
                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    $table->dropForeign(['reverses_id']);
                }
                $table->dropColumn(['reverses_id', 'reversal_reason', 'reversed_at']);
            }
        });

        Schema::table('outgoing_items', function (Blueprint $table) {
            if (Schema::hasColumn('outgoing_items', 'reverses_id')) {
                if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                    $table->dropForeign(['reverses_id']);
                }
                $table->dropColumn(['reverses_id', 'reversal_reason', 'reversed_at']);
            }
        });
    }
};
