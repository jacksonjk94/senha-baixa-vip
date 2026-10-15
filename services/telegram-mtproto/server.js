import { createHash, randomUUID, timingSafeEqual } from 'node:crypto';
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
// Conexões de envio aquecidas: conectar do zero a cada disparo custa
// segundos (TCP+TLS+auth) e atrasa a chegada no minuto exato.
const SENDER_IDLE_MS = 2 * 60 * 1000;
const MAX_SENDERS = 50;
const TRANSPORT_ERROR_PREFIXES = ['CONNECTION_', 'TIMEOUT', 'NETWORK_', 'WEBSOCKET_', 'DISCONNECTED', 'EPIPE', 'ECONN'];

function hashSession(session) {
    return createHash('sha256').update(session).digest('hex');
}

function isTransportError(error) {
    const code = error?.errorMessage ?? error?.code ?? '';
    if (code === '') {
        return true;
    }
    return TRANSPORT_ERROR_PREFIXES.some((prefix) => String(code).startsWith(prefix));
}

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

function sendErrorResponse(error) {
    const code = error?.errorMessage ?? '';
    if (['SESSION_REVOKED', 'AUTH_KEY_UNREGISTERED', 'AUTH_KEY_INVALID', 'USER_DEACTIVATED', 'AUTH_KEY_DUPLICATED'].includes(code)) {
        return [401, 'A sessão do Telegram expirou ou foi encerrada em outro aparelho. Autorize a conta novamente.', true];
    }
    if (typeof code === 'string' && (code.startsWith('FLOOD_WAIT') || code.startsWith('FLOOD_PREMIUM_WAIT'))) {
        const segundos = Number(code.match(/\d+/)?.[0] ?? 0);
        return [429, segundos > 0 ? `O Telegram pediu uma pausa de ${formatarDuracao(segundos)} antes de enviar novamente.` : 'O Telegram pediu uma pausa antes de enviar novamente.', false];
    }
    if (['PEER_ID_INVALID', 'CHAT_ID_INVALID', 'CHANNEL_INVALID', 'CHAT_INVALID', 'USER_NOT_PARTICIPANT'].includes(code)) {
        return [422, 'O chat de destino não foi encontrado para esta conta. Confira se a conta participa do grupo.', false];
    }
    if (code === 'MESSAGE_TOO_LONG') {
        return [422, 'A mensagem excede o limite do Telegram (4096 caracteres).', false];
    }
    if (code === 'CHAT_WRITE_FORBIDDEN' || code === 'CHAT_SEND_POLL_FORBIDDEN') {
        return [403, 'A conta não tem permissão para escrever neste grupo.', false];
    }
    console.error('[mtproto] Erro de envio não mapeado:', code || error?.message || error);
    return [502, `Não foi possível enviar pelo Telegram. Tente novamente. (${code || error?.message || 'erro desconhecido'})`, false];
}

function validChatId(value) {
    return typeof value === 'string' && /^-?\d{1,20}$/.test(value.trim());
}

function ordenarMensagens(lista, limit) {
    return (Array.isArray(lista) ? lista : [])
        .filter((m) => m && typeof m.id !== 'undefined')
        .sort((a, b) => (Number(b.date) || 0) - (Number(a.date) || 0))
        .slice(0, limit)
        .map((m) => ({
            id: String(m.id),
            texto: String(m.message ?? '').slice(0, 200),
            data: m.date ?? null,
            saida: m.out === true,
        }));
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
    clientFactory = (id, hash, sessionString = '') => new TelegramClient(new StringSession(sessionString), id, hash, {
        connectionRetries: 3,
        floodSleepThreshold: 0,
    }),
    now = Date.now,
} = {}) {
    const pending = new Map();
    const senders = new Map(); // hash da sessão -> { client, lastUsed }

    async function closeSender(key) {
        const entry = senders.get(key);
        senders.delete(key);
        if (!entry) {
            return;
        }
        try {
            await entry.client.disconnect();
        } catch {
            // Conexão descartável.
        }
    }

    function dropSender(session) {
        return closeSender(hashSession(session));
    }

    async function senderClient(session) {
        const key = hashSession(session);
        const existing = senders.get(key);

        if (existing) {
            senders.delete(key);
            senders.set(key, existing);
            existing.lastUsed = now();
            return existing.client;
        }

        if (senders.size >= MAX_SENDERS) {
            await closeSender(senders.keys().next().value);
        }

        const client = clientFactory(Number(apiId), apiHash, session);
        await client.connect();
        senders.set(key, { client, lastUsed: now() });
        return client;
    }

    async function destroyOperation(operationId, operation, { logout = true } = {}) {
        pending.delete(operationId);
        operation.closed = true;

        try {
            if (logout && operation.authorized) {
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
        for (const [key, entry] of senders) {
            if (entry.lastUsed + SENDER_IDLE_MS <= now()) {
                void closeSender(key);
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

                // Sessão persistente: o Laravel guarda o StringSession
                // (criptografado) para envios futuros como a conta do usuário.
                // Apenas desconecta — sem logout — para manter a autorização.
                const session = operation.client.session.save();
                await destroyOperation(operationId, operation, { logout: false });

                return responseJson(response, 200, {
                    user: {
                        id: String(me.id),
                        username: me.username ?? null,
                        firstName: me.firstName ?? null,
                    },
                    groups,
                    session,
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

        if (url.pathname === '/v1/messages/send') {
            const session = typeof body.session === 'string' ? body.session : '';
            const chatId = typeof body.chatId === 'string' ? body.chatId.trim() : '';
            const message = typeof body.message === 'string' ? body.message : '';

            if (session === '') {
                return responseJson(response, 422, { message: 'Sessão do Telegram ausente. Autorize a conta novamente.', sessionInvalid: true });
            }
            if (!validChatId(chatId)) {
                return responseJson(response, 422, { message: 'Chat de destino inválido.' });
            }
            if (message.trim() === '' || message.length > 4096) {
                return responseJson(response, 422, { message: 'Mensagem vazia ou acima do limite do Telegram (4096 caracteres).' });
            }

            let client;
            try {
                client = await senderClient(session);
            } catch (error) {
                const [status, errorMessage, sessionInvalid] = sendErrorResponse(error);
                return responseJson(response, status, { message: errorMessage, sessionInvalid });
            }

            try {
                const entity = await client.getEntity(Number(chatId));
                const sent = await client.sendMessage(entity, { message });
                return responseJson(response, 200, { ok: true, messageId: sent?.id ?? null });
            } catch (error) {
                await dropSender(session);

                if (isTransportError(error)) {
                    try {
                        const retry = await senderClient(session);
                        const entity = await retry.getEntity(Number(chatId));
                        const sent = await retry.sendMessage(entity, { message });
                        return responseJson(response, 200, { ok: true, messageId: sent?.id ?? null });
                    } catch (retryError) {
                        await dropSender(session);
                        const [retryStatus, retryMessage, retryInvalid] = sendErrorResponse(retryError);
                        return responseJson(response, retryStatus, { message: retryMessage, sessionInvalid: retryInvalid });
                    }
                }

                const [status, errorMessage, sessionInvalid] = sendErrorResponse(error);
                return responseJson(response, status, { message: errorMessage, sessionInvalid });
            }
        }

        if (url.pathname === '/v1/messages/history') {
            const session = typeof body.session === 'string' ? body.session : '';
            const chatId = typeof body.chatId === 'string' ? body.chatId.trim() : '';
            const limit = Math.max(1, Math.min(30, Number(body.limit) || 10));

            if (session === '') {
                return responseJson(response, 422, { message: 'Sessão do Telegram ausente. Autorize a conta novamente.', sessionInvalid: true });
            }
            if (!validChatId(chatId)) {
                return responseJson(response, 422, { message: 'Chat de destino inválido.' });
            }

            let client;
            try {
                client = await senderClient(session);
            } catch (error) {
                const [status, errorMessage, sessionInvalid] = sendErrorResponse(error);
                return responseJson(response, status, { message: errorMessage, sessionInvalid });
            }

            try {
                const entity = await client.getEntity(Number(chatId));
                const raw = await client.getMessages(entity, { limit });
                const list = Array.isArray(raw) ? raw : (raw?.messages ?? []);
                // Ordem canônica: mais recentes primeiro (a biblioteca não
                // garante a ordem) — a posição é contada a partir daí.
                const mensagens = ordenarMensagens(list, limit);
                return responseJson(response, 200, { ok: true, mensagens });
            } catch (error) {
                await dropSender(session);

                if (isTransportError(error)) {
                    try {
                        const retry = await senderClient(session);
                        const entity = await retry.getEntity(Number(chatId));
                        const raw = await retry.getMessages(entity, { limit });
                        const list = Array.isArray(raw) ? raw : (raw?.messages ?? []);
                        const mensagens = ordenarMensagens(list, limit);
                        return responseJson(response, 200, { ok: true, mensagens });
                    } catch (retryError) {
                        await dropSender(session);
                        const [retryStatus, retryMessage, retryInvalid] = sendErrorResponse(retryError);
                        return responseJson(response, retryStatus, { message: retryMessage, sessionInvalid: retryInvalid });
                    }
                }

                const [status, errorMessage, sessionInvalid] = sendErrorResponse(error);
                return responseJson(response, status, { message: errorMessage, sessionInvalid });
            }
        }

        if (url.pathname === '/v1/auth/logout') {
            const session = typeof body.session === 'string' ? body.session : '';
            if (session === '') {
                return responseJson(response, 200, { ok: true });
            }

            await dropSender(session);

            const client = clientFactory(Number(apiId), apiHash, session);
            try {
                await client.connect();
                await client.logOut();
                return responseJson(response, 200, { ok: true });
            } catch (error) {
                const [status, errorMessage, sessionInvalid] = sendErrorResponse(error);
                return responseJson(response, status, { message: errorMessage, sessionInvalid });
            } finally {
                try {
                    await client.disconnect();
                } catch {
                    // Conexão descartável.
                }
            }
        }

        return responseJson(response, 404, { message: 'Rota não encontrada.' });
    });

    server.on('close', () => {
        clearInterval(cleanupTimer);
        for (const [operationId, operation] of pending) {
            void destroyOperation(operationId, operation);
        }
        for (const [key] of senders) {
            void closeSender(key);
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
