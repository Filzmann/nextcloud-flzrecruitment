import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const sourceUrl = new URL('../js/modules/api.js', import.meta.url)
const source = readFileSync(sourceUrl, 'utf8')

function loadApi({
    headToken,
    authToken,
    legacyToken,
    response = {
        ok: true,
        status: 200,
        json: async () => ({ ok: true }),
    },
}) {
    const requests = []
    const window = {
        document: {
            head: {
                dataset: headToken === undefined ? {} : { requesttoken: headToken },
            },
        },
        _nc_auth_requestToken: authToken,
        OC: {
            generateUrl: (path) => path,
            requestToken: legacyToken,
        },
        fetch: async (url, options) => {
            requests.push({ url, options })
            return typeof response === 'function' ? response(url, options) : response
        },
    }
    window.window = window
    const context = vm.createContext({
        window,
        document: window.document,
        fetch: window.fetch,
        console,
        Error,
        JSON,
    })
    vm.runInContext(source, context, { filename: fileURLToPath(sourceUrl) })
    return { api: window.RecruitmentApi, requests }
}

{
    const { api, requests } = loadApi({
        headToken: undefined,
        authToken: 'current-auth-token',
        legacyToken: 'stale-legacy-token',
    })
    const result = await api.bootstrap()

    assert.equal(requests.length, 1)
    assert.deepEqual(result, { ok: true })
    assert.equal(requests[0].url, '/apps/adrecruitment/api/bootstrap')
    assert.equal(requests[0].options.method, 'GET')
    assert.equal(requests[0].options.credentials, 'same-origin')
    assert.equal(requests[0].options.body, null)
    assert.equal(requests[0].options.headers.Accept, 'application/json')
    assert.equal('Content-Type' in requests[0].options.headers, false)
    assert.equal('requesttoken' in requests[0].options.headers, false)
    assert.equal('X-Requested-With' in requests[0].options.headers, false)
}

{
    const { api, requests } = loadApi({
        authToken: 'current-auth-token',
        headToken: 'current-head-token',
        legacyToken: 'stale-legacy-token',
    })
    const cases = [
        [() => api.application(7), '/api/applications/7', 'GET', null],
        [() => api.template(8), '/api/templates/8', 'GET', null],
        [() => api.createJob({ title: 'Entwicklung' }), '/api/jobs', 'POST', { title: 'Entwicklung' }],
        [() => api.createPerson({ givenName: 'Alex' }), '/api/people', 'POST', { givenName: 'Alex' }],
        [() => api.createApplication({ personId: 4 }), '/api/applications', 'POST', { personId: 4 }],
        [() => api.createTemplate({ name: 'Erstgespräch' }), '/api/templates', 'POST', { name: 'Erstgespräch' }],
        [() => api.createQuestion(9, { prompt: 'Warum?' }), '/api/templates/9/questions', 'POST', { prompt: 'Warum?' }],
        [() => api.updateQuestion(10, { prompt: 'Weshalb?' }), '/api/questions/10', 'PUT', { prompt: 'Weshalb?' }],
        [() => api.createBubble(11, { label: 'Gut' }), '/api/questions/11/bubbles', 'POST', { label: 'Gut' }],
        [() => api.createInterview(12, 13), '/api/applications/12/interviews', 'POST', { templateId: 13 }],
        [() => api.saveDraft(14, { 1: 'Antwort' }, 2), '/api/interviews/14/draft', 'PUT', { answers: { 1: 'Antwort' }, version: 2 }],
        [() => api.completeInterview(15, { 2: 'Ja' }, 3), '/api/interviews/15/complete', 'POST', { answers: { 2: 'Ja' }, version: 3 }],
        [() => api.transitionStatus(16, 'screening', 4), '/api/applications/16/status', 'POST', { status: 'screening', version: 4 }],
    ]

    for (const [invoke, path, method, body] of cases) {
        const index = requests.length
        await invoke()
        const request = requests[index]
        assert.equal(request.url, '/apps/adrecruitment' + path)
        assert.equal(request.options.method, method)
        assert.equal(request.options.credentials, 'same-origin')
        assert.equal(request.options.body, body === null ? null : JSON.stringify(body))
        if (method === 'GET') {
            assert.equal('requesttoken' in request.options.headers, false)
        } else {
            assert.equal(request.options.headers.requesttoken, 'current-auth-token')
            assert.equal(request.options.headers['X-Requested-With'], 'XMLHttpRequest')
            assert.equal(request.options.headers['Content-Type'], 'application/json')
        }
    }
}

for (const tokenCase of [
    { headToken: 'head-token', authToken: undefined, legacyToken: 'legacy-token', expected: 'head-token' },
    { headToken: undefined, authToken: undefined, legacyToken: 'legacy-token', expected: 'legacy-token' },
]) {
    const { api, requests } = loadApi(tokenCase)
    await api.createPerson({ givenName: 'Alex', familyName: 'Beispiel' })
    assert.equal(requests[0].options.headers.requesttoken, tokenCase.expected)
}

{
    const { api, requests } = loadApi({
        headToken: undefined,
        authToken: undefined,
        legacyToken: undefined,
    })

    await assert.rejects(
        () => api.createPerson({ givenName: 'Alex', familyName: 'Beispiel' }),
        /CSRF-Token/,
    )
    assert.equal(requests.length, 0)
}

{
    const { api } = loadApi({
        authToken: 'token',
        response: {
            ok: false,
            status: 422,
            json: async () => ({ message: 'Eingabe ist ungültig.' }),
        },
    })

    await assert.rejects(
        () => api.createPerson({ givenName: '' }),
        (error) => error.message === 'Eingabe ist ungültig.' && error.status === 422,
    )
}

{
    const { api } = loadApi({
        response: {
            ok: false,
            status: 503,
            json: async () => {
                throw new Error('Keine JSON-Antwort')
            },
        },
    })

    await assert.rejects(
        () => api.bootstrap(),
        (error) => error.message === 'Die Anfrage ist fehlgeschlagen.' && error.status === 503,
    )
}

{
    const { api } = loadApi({
        response: {
            ok: true,
            status: 204,
            json: async () => {
                throw new Error('Leere Antwort')
            },
        },
    })

    assert.deepEqual(Object.keys(await api.bootstrap()), [])
}
