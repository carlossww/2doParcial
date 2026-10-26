import test from 'node:test';
import assert from 'node:assert/strict';
import { bindLogoutForm, requestLogout } from '../../public/assets/logout.js';

const options = { url: '/salir', csrfToken: 'csrf-prueba', loginUrl: '/ingresar', confirmUrl: '/salir/confirmar' };
const http = (status) => ({ status, ok: status >= 200 && status < 300 });

test('cierre usa POST autenticado con CSRF y vuelve al ingreso', async () => {
    let received;
    const target = await requestLogout(options, async (...args) => { received = args; return http(200); });
    assert.equal(target, '/ingresar');
    assert.equal(received[0], '/salir');
    assert.equal(received[1].method, 'POST');
    assert.equal(received[1].credentials, 'same-origin');
    assert.equal(received[1].headers.Accept, 'application/json');
    assert.equal(received[1].headers['X-CSRF-TOKEN'], 'csrf-prueba');
});

test('un fallo de red tiene un solo reintento automático', async () => {
    let calls = 0;
    assert.equal(await requestLogout(options, async () => {
        calls += 1;
        if (calls === 1) throw new TypeError('Failed to fetch');
        return http(200);
    }), '/ingresar');
    assert.equal(calls, 2);
});

test('un fallo persistente termina después de dos intentos', async () => {
    let calls = 0;
    await assert.rejects(requestLogout(options, async () => {
        calls += 1;
        throw new TypeError('Failed to fetch');
    }), /conexión/);
    assert.equal(calls, 2);
});

for (const status of [403, 422, 429, 500]) {
    test(`una respuesta HTTP ${status} no se reintenta automáticamente`, async () => {
        let calls = 0;
        await assert.rejects(requestLogout(options, async () => { calls += 1; return http(status); }), /No pudimos cerrar/);
        assert.equal(calls, 1);
    });
}

test('un error HTTP después del fallo de red no autoriza un tercer intento', async () => {
    let calls = 0;
    await assert.rejects(requestLogout(options, async () => {
        calls += 1;
        if (calls === 1) throw new TypeError('Failed to fetch');
        return http(500);
    }), /No pudimos cerrar/);
    assert.equal(calls, 2);
});

test('sesión terminada vuelve al ingreso y CSRF vencido se comprueba mediante GET de confirmación', async () => {
    assert.equal(await requestLogout(options, async () => http(401)), '/ingresar');
    assert.equal(await requestLogout(options, async () => http(419)), '/salir/confirmar');
});

test('errores ajenos a la red no se reintentan', async () => {
    let calls = 0;
    await assert.rejects(requestLogout(options, async () => {
        calls += 1;
        throw new Error('error de programación');
    }), /error de programación/);
    assert.equal(calls, 1);
});

function formFixture() {
    const listeners = {};
    const attributes = new Map();
    const button = { textContent: 'Sí, cerrar sesión', disabled: false };
    const errorBox = { hidden: true, textContent: '', focused: false, focus() { this.focused = true; } };
    const token = { value: options.csrfToken };
    const form = {
        action: options.url,
        dataset: { loginUrl: options.loginUrl, confirmUrl: options.confirmUrl },
        querySelector(selector) {
            return selector === 'button[type="submit"]' ? button : selector === '[data-logout-error]' ? errorBox : token;
        },
        setAttribute(name, value) { attributes.set(name, value); },
        removeAttribute(name) { attributes.delete(name); },
        addEventListener(name, handler) { listeners[name] = handler; },
    };
    return { form, button, errorBox, attributes, submit: () => listeners.submit({ preventDefault() {} }) };
}

test('doble envío del formulario no duplica la petición y comunica el progreso', async () => {
    const fixture = formFixture();
    let finish;
    let calls = 0;
    const navigations = [];
    bindLogoutForm(fixture.form, {
        fetcher: () => { calls += 1; return new Promise((resolve) => { finish = resolve; }); },
        navigate: (url) => navigations.push(url),
    });
    const first = fixture.submit();
    assert.equal(fixture.button.disabled, true);
    assert.equal(fixture.attributes.get('aria-busy'), 'true');
    await fixture.submit();
    assert.equal(calls, 1);
    finish(http(200));
    await first;
    assert.deepEqual(navigations, ['/ingresar']);
});

test('tras fallo persistente muestra error accesible y permite reintento manual', async () => {
    const fixture = formFixture();
    let calls = 0;
    const navigations = [];
    bindLogoutForm(fixture.form, {
        fetcher: async () => {
            calls += 1;
            if (calls <= 2) throw new TypeError('Failed to fetch');
            return http(200);
        },
        navigate: (url) => navigations.push(url),
    });
    await fixture.submit();
    assert.equal(calls, 2);
    assert.equal(fixture.errorBox.hidden, false);
    assert.equal(fixture.errorBox.focused, true);
    assert.match(fixture.errorBox.textContent, /conexión/);
    assert.equal(fixture.button.disabled, false);
    assert.equal(fixture.button.textContent, 'Sí, cerrar sesión');
    assert.equal(fixture.attributes.has('aria-busy'), false);
    await fixture.submit();
    assert.equal(calls, 3);
    assert.equal(fixture.errorBox.hidden, true);
    assert.deepEqual(navigations, ['/ingresar']);
});
