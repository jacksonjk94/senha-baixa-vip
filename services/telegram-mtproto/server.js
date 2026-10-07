import { randomUUID, timingSafeEqual } from 'node:crypto';
import { createServer as createHttpServer } from 'node:http';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import dotenv from 'dotenv';
import { Api, TelegramClient } from 'teleproto';
import { StringSession } from 'teleproto/sessions';

const serviceDirectory = dirname(fileURLToPath(import.meta.url));
const mtprotoEnvironmentKeys = [
    'TELEGRAM_API_ID',
    'TELEGRAM_API_HASH',
    'TELEGRAM_MTPROTO_PORT',
    'TELEGRAM_MTPROTO_HOST',
    'TELEGRAM_MTPROTO_SERVICE_TOKEN',
];

for (const path of [resolve(serviceDirectory, '../../.env'), resolve(serviceDirectory, '.env')]) {
    if (!existsSync(path)) {
        continue;
    }

    const values = dotenv.parse(readFileSync(path));
    for (const key of mtprotoEnvironmentKeys) {
        if ((!process.env[key] || process.env[key] === '') && values[key]) {
            process.env[key] = values[key];
        }
    }
}

const OPERATION_TTL_MS = 10 * 60 * 1000;
const MAX_BODY_BYTES = 16 * 1024;

export function groupsFromDialogs(dialogs) {
    const groups = [];

    for (const dialog of dialogs) {
        if (!dialog.entity) {
            continue;
        }

        const isBasicGroup = dialog.entity.className === 'Chat';
        const isSupergroup = dialog.entity.className === 'Channel' && dialog.entity.megagroup === true;
        if (!isBasicGroup && !isSupergroup) {
            continue;
        }

        groups.push({
            chat_id: String(dialog.id),
            nome: dialog.title || dialog.entity.title || `Grupo ${dialog.id}`,
            tipo: isBasicGroup ? 'group' : 'supergroup',
        });
    }

    return groups;
}

function responseJson(response, status, payload) {
    response.writeHead(status, {
        'content-type': 'application/json; charset=utf-8',
        'cache-control': 'no-store',
    });
    response.end(JSON.stringify(payload));
}

async function readJson(request) {
    const chunks = [];
    let size = 0;

    for await (const chunk of request) {
        size += chunk.length;
        if (size > MAX_BODY_BYTES) {
            const error = new Error('Payload muito grande.');
            error.status = 413;
            throw error;
        }
        chunks.push(chunk);
    }

    try {
        return JSON.parse(Buffer.concat(chunks).toString('utf8'));
    } catch {
        const error = new Error('JSON inválido.');
        error.status = 400;
        throw error;
    }
}

function formatarDuracao(totalSegundos) {
    const s = Math.max(0, Math.floor(totalSegundos));
    const horas = Math.floor(s / 3600);
    const minutos = Math.floor((s % 3600) / 60);
    const segundos = s % 60;
    const partes = [];
    if (horas > 0) partes.push(`${horas} ${horas === 1 ? 'hora' : 'horas'}`);
    if (minutos > 0) partes.push(`${minutos} ${minutos === 1 ? 'minuto' : 'minutos'}`);
    if (segundos > 0 || partes.length === 0) partes.push(`${segundos} ${segundos === 1 ? 'segundo' : 'segundos'}`);
    return partes.join(', ');
}

function errorResponse(error) {
    switch (error?.errorMessage) {
        case 'PHONE_NUMBER_INVALID':
            return [422, 'Número de telefone inválido. Use o formato internacional.'];
        case 'API_ID_INVALID':
            return [422, 'Credenciais da API do Telegram inválidas. Confira TELEGRAM_API_ID e TELEGRAM_API_HASH.'];
        case 'PHONE_NUMBER_BANNED':
            return [422, 'O Telegram não permite iniciar sessão com este número.'];
        case 'PHONE_CODE_INVALID':
        case 'PHONE_CODE_EMPTY':
            return [422, 'Código Telegram inválido. Confira o código e tente novamente.'];
        case 'PHONE_CODE_EXPIRED':
            return [410, 'O código expirou. Solicite outro código.'];
        case 'PASSWORD_HASH_INVALID':
            return [422, 'Senha de verificação em duas etapas incorreta.'];
        default:
            if (typeof error?.errorMessage === 'string' && (error.errorMessage.startsWith('FLOOD_WAIT') || error.errorMessage.startsWith('FLOOD_PREMIUM_WAIT'))) {
                const segundos = Number(error.errorMessage.match(/\d+/)?.[0] ?? 0);
                return [429, segundos > 0 ? `Muitas tentativas. O Telegram pediu para aguardar ${formatarDuracao(segundos)} antes de tentar novamente.` : 'O Telegram pediu uma pausa antes de tentar novamente.'];
            }
            console.error('[mtproto] Erro não mapeado:', error?.errorMessage ?? error?.message ?? error);
            return [502, `Não foi possível concluir a autenticação no Telegram. Tente novamente. (${error?.errorMessage ?? error?.message ?? 'erro desconhecido'})`];
    }
}

export function createTelegramMtprotoServer({
    apiId = process.env.TELEGRAM_API_ID,
    apiHash = process.env.TELEGRAM_API_HASH,
    serviceToken = process.env.TELEGRAM_MTPROTO_SERVICE_TOKEN,
    clientFactory = (id, hash) => new TelegramClient(new StringSession(''), id, hash, {
        connectionRetries: 3,
        floodSleepThreshold: 0,
    }),
    now = Date.now,
} = {}) {
    const pending = new Map();

    async function destroyOperation(operationId, operation) {
        pending.delete(operationId);
        operation.closed = true;

        try {
            if (operation.authorized) {
                await operation.client.logOut();
            }
        } catch {
            // Never retain the authorization if Telegram logout cannot finish.
        } finally {
            try {
                await operation.client.disconnect();
            } catch {
                // The in-memory client is discarded regardless of disconnect errors.
            }
        }
    }

    const cleanupTimer = setInterval(() => {
        for (const [operationId, operation] of pending) {
            if (operation.expiresAt <= now()) {
                void destroyOperation(operationId, operation);
            }
        }
    }, 60_000);
    cleanupTimer.unref?.();

    const server = createHttpServer(async (request, response) => {
        const url = new URL(request.url ?? '/', 'http://127.0.0.1');

        if (request.method === 'GET' && url.pathname === '/health') {
            return responseJson(response, 200, { ok: true });
        }

        if (!serviceToken || !apiId || !apiHash) {
            return responseJson(response, 503, { message: 'Serviço MTProto não configurado.' });
        }

        const providedToken = request.headers.authorization?.replace(/^Bearer\s+/i, '') ?? '';
        const expectedBuffer = Buffer.from(serviceToken);
        const providedBuffer = Buffer.from(providedToken);
        if (expectedBuffer.length !== providedBuffer.length || !timingSafeEqual(expectedBuffer, providedBuffer)) {
            return responseJson(response, 401, { message: 'Não autorizado.' });
        }

        if (request.method !== 'POST') {
            return responseJson(response, 405, { message: 'Método não permitido.' });
        }

        let body;
        try {
            body = await readJson(request);
        } catch (error) {
            return responseJson(response, error.status ?? 400, { message: error.message });
        }

        if (url.pathname === '/v1/auth/send-code') {
            const phone = typeof body.phone === 'string' ? body.phone.trim() : '';
            if (!/^\+[1-9]\d{7,14}$/.test(phone)) {
                return responseJson(response, 422, { message: 'Informe o telefone em formato internacional.' });
            }

            if (pending.size >= 20) {
                return responseJson(response, 503, { message: 'Há muitas autenticações em andamento. Tente novamente em instantes.' });
            }

            const client = clientFactory(Number(apiId), apiHash);
            const operationId = randomUUID();
            const operation = {
                client,
                phone,
                phoneCodeHash: null,
                authorized: false,
                awaitingPassword: false,
                expiresAt: now() + OPERATION_TTL_MS,
            };

            try {
                await client.connect();
                const sentCode = await client.sendCode({ apiId: Number(apiId), apiHash }, phone);

                if (sentCode.emailRequired || sentCode.emailCodeSent) {
                    await client.disconnect();
                    return responseJson(response, 422, {
                        message: 'Esta conta exige uma etapa de verificação por e-mail, ainda não disponível neste fluxo.',
                    });
                }

                operation.phoneCodeHash = sentCode.phoneCodeHash;
                pending.set(operationId, operation);

                return responseJson(response, 200, {
                    operationId,
                    isCodeViaApp: sentCode.isCodeViaApp,
                    expiresIn: Math.floor(OPERATION_TTL_MS / 1000),
                });
            } catch (error) {
                try {
                    await client.disconnect();
                } catch {
                    // No session is retained on a failed code request.
                }
                const [status, message] = errorResponse(error);
                return responseJson(response, status, { message });
            }
        }

        if (url.pathname === '/v1/auth/verify-code') {
            const operationId = typeof body.operationId === 'string' ? body.operationId : '';
            const code = typeof body.code === 'string' ? body.code.trim() : '';
            const operation = pending.get(operationId);

            if (!operation || operation.expiresAt <= now()) {
                if (operation) {
                    await destroyOperation(operationId, operation);
                }
                return responseJson(response, 410, { message: 'A sessão de login expirou. Comece novamente.' });
            }

            if (!operation.awaitingPassword && !/^\d{4,8}$/.test(code)) {
                return responseJson(response, 422, { message: 'Informe o código recebido do Telegram.' });
            }

            try {
                if (!operation.awaitingPassword) {
                    try {
                        await operation.client.invoke(new Api.auth.SignIn({
                            phoneNumber: operation.phone,
                            phoneCodeHash: operation.phoneCodeHash,
                            phoneCode: code,
                        }));
                        operation.authorized = true;
                    } catch (error) {
                        if (error?.errorMessage === 'SESSION_PASSWORD_NEEDED') {
                            operation.awaitingPassword = true;
                            operation.expiresAt = now() + OPERATION_TTL_MS;
                            return responseJson(response, 200, { needsPassword: true });
                        }
                        throw error;
                    }
                } else {
                    const password = typeof body.password === 'string' ? body.password : '';
                    if (!password) {
                        return responseJson(response, 422, { message: 'Informe a senha da verificação em duas etapas.' });
                    }

                    await operation.client.signInWithPassword(
                        { apiId: Number(apiId), apiHash },
                        {
                            password: async () => password,
                            onError: async (error) => { throw error; },
                        }
                    );
                    operation.authorized = true;
                }

                const me = await operation.client.getMe();
                const groups = [];
                for await (const dialog of operation.client.iterDialogs({})) {
                    if (!dialog.entity) {
                        continue;
                    }

                    const isBasicGroup = dialog.entity.className === 'Chat';
                    const isSupergroup = dialog.entity.className === 'Channel' && dialog.entity.megagroup === true;
                    if (!isBasicGroup && !isSupergroup) {
                        continue;
                    }
                    groups.push({
                        chat_id: String(dialog.id),
                        nome: dialog.title || dialog.entity.title || `Grupo ${dialog.id}`,
                        tipo: isBasicGroup ? 'group' : 'supergroup',
                    });
                }

                await destroyOperation(operationId, operation);

                return responseJson(response, 200, {
                    user: {
                        id: String(me.id),
                        username: me.username ?? null,
                        firstName: me.firstName ?? null,
                    },
                    groups,
                });
            } catch (error) {
                if (error?.errorMessage === 'PHONE_CODE_INVALID' || error?.errorMessage === 'PASSWORD_HASH_INVALID') {
                    return responseJson(response, 422, {
                        message: error.errorMessage === 'PASSWORD_HASH_INVALID'
                            ? 'Senha de verificação em duas etapas incorreta.'
                            : 'Código Telegram inválido. Confira e tente novamente.',
                        needsPassword: operation.awaitingPassword,
                    });
                }

                await destroyOperation(operationId, operation);
                const [status, message] = errorResponse(error);
                return responseJson(response, status, { message });
            }
        }

        return responseJson(response, 404, { message: 'Rota não encontrada.' });
    });

    server.on('close', () => {
        clearInterval(cleanupTimer);
        for (const [operationId, operation] of pending) {
            void destroyOperation(operationId, operation);
        }
    });

    return server;
}

if (process.argv[1] && fileURLToPath(import.meta.url) === resolve(process.argv[1])) {
    const server = createTelegramMtprotoServer();
    const port = Number(process.env.TELEGRAM_MTPROTO_PORT || 3037);
    const host = process.env.TELEGRAM_MTPROTO_HOST || '127.0.0.1';

    server.listen(port, host, () => {
        console.log(`Telegram MTProto service listening on ${host}:${port}`);
    });
}
