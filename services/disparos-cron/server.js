import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const serviceDirectory = dirname(fileURLToPath(import.meta.url));

for (const path of [resolve(serviceDirectory, '../../.env'), resolve(serviceDirectory, '.env')]) {
    if (!existsSync(path)) {
        continue;
    }

    for (const line of readFileSync(path, 'utf8').split(/\r?\n/)) {
        const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*)\s*$/);
        if (!match) {
            continue;
        }
        const [, key, rawValue] = match;
        if (process.env[key]) {
            continue;
        }
        process.env[key] = rawValue.replace(/^["']|["']$/g, '');
    }
}

const BASE_URL = (process.env.DISPAROS_CRON_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const SECRET = process.env.N8N_WORKER_SECRET || '';
const ROTA = '/api/n8n/disparos/processar';
const ROTA_PRECISAO = '/api/n8n/disparos/precisao';
const PRECISAO_TTL_MS = 5 * 60 * 1000;
const MAX_LATENCIA_MS = 10000;

// Parâmetros de precisão vindos do painel admin (cacheados).
let precisao = { latenciaAtiva: false, latenciaMs: 0, obtidaEm: 0 };

export function msAteDisparo(agoraMs, latenciaMs) {
    const latencia = Math.max(0, Math.min(MAX_LATENCIA_MS, Math.floor(latenciaMs || 0)));
    const proximoMinuto = Math.ceil((agoraMs + 1) / 60000) * 60000;
    return Math.max(0, proximoMinuto - latencia - agoraMs);
}

async function atualizarPrecisao() {
    try {
        const response = await fetch(`${BASE_URL}${ROTA_PRECISAO}`, {
            headers: { 'X-N8N-Secret': SECRET, accept: 'application/json' },
        });
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        const dados = await response.json();
        precisao = {
            latenciaAtiva: dados.latencia_ativa !== false,
            latenciaMs: Math.max(0, Math.min(MAX_LATENCIA_MS, Number(dados.latencia_ms) || 0)),
            obtidaEm: Date.now(),
        };
    } catch (error) {
        console.error(`[${new Date().toISOString()}] Precisão indisponível (${error.message}); mantendo anterior.`);
    }
}

function latenciaEfetiva() {
    return precisao.latenciaAtiva ? precisao.latenciaMs : 0;
}

async function disparar() {
    const inicio = new Date();
    try {
        const response = await fetch(`${BASE_URL}${ROTA}`, {
            headers: { 'X-N8N-Secret': SECRET, accept: 'application/json' },
        });
        const corpo = await response.text();
        console.log(`[${inicio.toISOString()}] ${response.status} latencia=${latenciaEfetiva()}ms ${corpo.slice(0, 300)}`);
    } catch (error) {
        console.error(`[${inicio.toISOString()}] ERRO ${error.message}`);
    }
}

console.log(`Disparos cron → ${BASE_URL}${ROTA} (a cada minuto, adiantado pela latência)`);

if (process.argv[1] && fileURLToPath(import.meta.url) === resolve(process.argv[1])) {
    if (process.argv.includes('--agora')) {
        await atualizarPrecisao();
        await disparar();
        process.exit(0);
    } else {
        await atualizarPrecisao();
        setInterval(() => { void atualizarPrecisao(); }, PRECISAO_TTL_MS).unref?.();
        (async function ciclo() {
            for (;;) {
                await new Promise((resolve) => setTimeout(resolve, msAteDisparo(Date.now(), latenciaEfetiva())));
                await disparar();
            }
        })();
    }
}
