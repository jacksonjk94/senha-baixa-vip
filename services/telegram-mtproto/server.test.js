import test from 'node:test';
import assert from 'node:assert/strict';
import { createTelegramMtprotoServer, groupsFromDialogs } from './server.js';

test('groupsFromDialogs keeps basic groups and supergroups only', () => {
    const groups = groupsFromDialogs([
        { id: -7, title: 'Grupo básico', isGroup: true, entity: { className: 'Chat', title: 'Grupo básico' } },
        { id: '-10042', title: 'Supergrupo', isGroup: true, entity: { className: 'Channel', megagroup: true } },
        { id: '-10043', title: 'Canal', isGroup: false, isChannel: true, entity: { className: 'Channel', megagroup: false } },
        { id: 99, title: 'Conversa privada', isGroup: false, isUser: true, entity: { className: 'User' } },
    ]);

    assert.deepEqual(groups, [
        { chat_id: '-7', nome: 'Grupo básico', tipo: 'group' },
        { chat_id: '-10042', nome: 'Supergrupo', tipo: 'supergroup' },
    ]);
});

test('authenticates the internal API and completes a user login without persisting a session', async (context) => {
    let loggedOut = false;
    const fakeClient = {
        async connect() {},
        async sendCode() {
            return { phoneCodeHash: 'hash-from-telegram', isCodeViaApp: true };
        },
        async invoke() {
            return {};
        },
        async getMe() {
            return { id: 123456, username: 'maria', firstName: 'Maria' };
        },
        async *iterDialogs() {
            yield { id: -20, title: 'Grupo de teste', isGroup: true, entity: { className: 'Chat' } };
            yield { id: 55, title: 'Conversa privada', isGroup: false, entity: { className: 'User' } };
        },
        async logOut() {
            loggedOut = true;
            return true;
        },
        async disconnect() {},
    };

    const server = createTelegramMtprotoServer({
        apiId: 123,
        apiHash: 'test-hash',
        serviceToken: 'internal-test-token',
        clientFactory: () => fakeClient,
    });
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    context.after(() => new Promise((resolve) => server.close(resolve)));

    const baseUrl = `http://127.0.0.1:${server.address().port}`;
    const unauthorized = await fetch(`${baseUrl}/v1/auth/send-code`, {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ phone: '+5511999999999' }),
    });
    assert.equal(unauthorized.status, 401);

    const headers = {
        authorization: 'Bearer internal-test-token',
        'content-type': 'application/json',
    };
    const started = await fetch(`${baseUrl}/v1/auth/send-code`, {
        method: 'POST',
        headers,
        body: JSON.stringify({ phone: '+5511999999999' }),
    });
    assert.equal(started.status, 200);
    const operation = await started.json();

    const completed = await fetch(`${baseUrl}/v1/auth/verify-code`, {
        method: 'POST',
        headers,
        body: JSON.stringify({ operationId: operation.operationId, code: '12345' }),
    });
    assert.equal(completed.status, 200);
    assert.deepEqual(await completed.json(), {
        user: { id: '123456', username: 'maria', firstName: 'Maria' },
        groups: [{ chat_id: '-20', nome: 'Grupo de teste', tipo: 'group' }],
    });
    assert.equal(loggedOut, true);
});

test('keeps a login challenge alive for a second-factor password', async (context) => {
    let passwordAccepted = false;
    const fakeClient = {
        async connect() {},
        async sendCode() {
            return { phoneCodeHash: 'hash-from-telegram', isCodeViaApp: false };
        },
        async invoke() {
            const error = new Error('2FA required');
            error.errorMessage = 'SESSION_PASSWORD_NEEDED';
            throw error;
        },
        async signInWithPassword(_credentials, authParams) {
            const password = await authParams.password();
            if (password !== 'test-password') {
                const error = new Error('Bad password');
                error.errorMessage = 'PASSWORD_HASH_INVALID';
                throw error;
            }
            passwordAccepted = true;
        },
        async getMe() {
            return { id: 123456, username: 'maria', firstName: 'Maria' };
        },
        async *iterDialogs() {},
        async logOut() { return true; },
        async disconnect() {},
    };

    const server = createTelegramMtprotoServer({
        apiId: 123,
        apiHash: 'test-hash',
        serviceToken: 'internal-test-token',
        clientFactory: () => fakeClient,
    });
    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    context.after(() => new Promise((resolve) => server.close(resolve)));

    const baseUrl = `http://127.0.0.1:${server.address().port}`;
    const headers = {
        authorization: 'Bearer internal-test-token',
        'content-type': 'application/json',
    };
    const started = await fetch(`${baseUrl}/v1/auth/send-code`, {
        method: 'POST',
        headers,
        body: JSON.stringify({ phone: '+5511999999999' }),
    });
    const operation = await started.json();

    const passwordRequired = await fetch(`${baseUrl}/v1/auth/verify-code`, {
        method: 'POST',
        headers,
        body: JSON.stringify({ operationId: operation.operationId, code: '12345' }),
    });
    assert.deepEqual(await passwordRequired.json(), { needsPassword: true });
    assert.equal(passwordAccepted, false);

    const completed = await fetch(`${baseUrl}/v1/auth/verify-code`, {
        method: 'POST',
        headers,
        body: JSON.stringify({ operationId: operation.operationId, password: 'test-password' }),
    });
    assert.equal(completed.status, 200);
    assert.equal(passwordAccepted, true);
});
