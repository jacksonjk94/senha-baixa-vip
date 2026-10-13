import test from 'node:test';
import assert from 'node:assert/strict';
import { msAteDisparo } from './server.js';

test('fires at second zero without latency', () => {
    // 12:16:20.000 → faltam 40s para 12:17:00.
    const agora = Date.UTC(2026, 9, 13, 12, 16, 20, 0);
    assert.equal(msAteDisparo(agora, 0), 40000);
});

test('fires early by the measured latency', () => {
    const agora = Date.UTC(2026, 9, 13, 12, 16, 20, 0);
    assert.equal(msAteDisparo(agora, 250), 39750);
});

test('never waits a full minute and clamps latency', () => {
    // Em cima do :00, sem latência: próximo minuto (60s), nunca 0 travado.
    const emCima = Date.UTC(2026, 9, 13, 12, 17, 0, 0);
    assert.equal(msAteDisparo(emCima, 0), 60000);
    // Latência absurda é limitada a 10s.
    const agora = Date.UTC(2026, 9, 13, 12, 16, 20, 0);
    assert.equal(msAteDisparo(agora, 999999), 30000);
    // Latência negativa vira 0.
    assert.equal(msAteDisparo(agora, -500), 40000);
});
