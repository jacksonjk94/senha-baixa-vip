<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sessão MTProto da conta do usuário (StringSession criptografada pelo
     * cast `encrypted`), usada para disparos como a própria conta em vez do
     * bot. Guardada somente após autorização explícita no fluxo MTProto.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('telegram_mtproto_session')->nullable()->after('telefone');
            $table->string('telegram_mtproto_phone')->nullable()->after('telegram_mtproto_session');
            $table->timestamp('telegram_mtproto_conectado_em')->nullable()->after('telegram_mtproto_phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'telegram_mtproto_session',
                'telegram_mtproto_phone',
                'telegram_mtproto_conectado_em',
            ]);
        });
    }
};
