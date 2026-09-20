<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            /*
             | Set once the thread belongs to an account, which is what lets the
             | history follow the person into another browser. Guest threads keep
             | using `owner_key` and leave this null.
             */
            $table->foreignId('user_id')->nullable()->after('id')
                ->constrained()->cascadeOnDelete();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'updated_at']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
