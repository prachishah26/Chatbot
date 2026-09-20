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
            // Derived from the first user message, shown in the sidebar.
            $table->string('title')->nullable()->after('id');

            // Scopes a thread to one visitor without requiring an account.
            $table->string('owner_key', 64)->nullable()->after('title');

            $table->index(['owner_key', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex(['owner_key', 'updated_at']);
            $table->dropColumn(['title', 'owner_key']);
        });
    }
};
