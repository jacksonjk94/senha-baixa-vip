<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupo_telegrams', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('chat_id')->index();
            $table->string('tipo')->default('group'); // group | supergroup | channel
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['chat_id', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grupo_telegrams');
    }
};
