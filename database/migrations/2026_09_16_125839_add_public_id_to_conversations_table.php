<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            /*
             | What the URL carries, so a thread's address reveals nothing about
             | how many exist or which one came first. The auto-increment id stays
             | internal and is never rendered.
             */
            $table->string('public_id', 26)->nullable()->after('id');
        });

        DB::table('conversations')->whereNull('public_id')->orderBy('id')
            ->each(static function (object $row): void {
                DB::table('conversations')->where('id', $row->id)
                    ->update(['public_id' => (string) Str::ulid()]);
            });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('public_id', 26)->nullable(false)->change();
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
