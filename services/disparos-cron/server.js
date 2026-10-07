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

async function disparar() {
    const inicio = new Date();
    try {
        const response = await fetch(`${BASE_URL}${ROTA}`, {
            headers: { 'X-N8N-Secret': SECRET, accept: 'application/json' },
        });
        const corpo = await response.text();
        console.log(`[${inicio.toISOString()}] ${response.status} ${corpo.slice(0, 300)}`);
    } catch (error) {
        console.error(`[${inicio.toISOString()}] ERRO ${error.message}`);
    }
}

function msAteVirarMinuto() {
    return 60000 - (Date.now() % 60000);
}

console.log(`Disparos cron → ${BASE_URL}${ROTA} (a cada minuto, no segundo 0)`);

if (process.argv.includes('--agora')) {
    await disparar();
    process.exit(0);
} else {
    (async function ciclo() {
        for (;;) {
            await new Promise((resolve) => setTimeout(resolve, msAteVirarMinuto()));
            await disparar();
        }
    })();
}
