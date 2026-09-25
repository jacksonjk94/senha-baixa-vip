<?php

namespace Tests\Feature;

use Tests\TestCase;

class AjudaBotTelegramTest extends TestCase
{
    public function test_pagina_de_ajuda_do_bot_e_publica(): void
    {
        $resposta = $this->get(route('ajuda.bot-telegram'));

        $resposta->assertOk();
        $resposta->assertSee('Como configurar o bot do Telegram');
        $resposta->assertSee('@BotFather');
    }
}
