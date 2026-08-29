<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('openai_conversation_id', 120)->nullable()->after('metadata');
            $table->index('openai_conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropIndex(['openai_conversation_id']);
            $table->dropColumn('openai_conversation_id');
        });
    }
};
