<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_id')->nullable()->unique()->after('username');
            $table->string('telegram_username')->nullable()->after('telegram_id');
            $table->text('telegram_token')->nullable()->after('telegram_username');
            $table->string('ultimos_digitos_telefone', 4)->nullable()->after('telegram_token');
            $table->string('telefone')->nullable()->after('ultimos_digitos_telefone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_id',
                'telegram_username',
                'telegram_token',
                'ultimos_digitos_telefone',
                'telefone',
            ]);
        });
    }
};
