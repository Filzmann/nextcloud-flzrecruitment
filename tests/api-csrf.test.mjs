import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import vm from 'node:vm'

const source = readFileSync(new URL('../js/modules/api.js', import.meta.url), 'utf8')

function loadApi({ headToken, authToken, legacyToken }) {
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
            return {
                ok: true,
                status: 200,
                json: async () => ({ ok: true }),
            }
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
    vm.runInContext(source, context)
    return { api: window.RecruitmentApi, requests }
}

{
    const { api, requests } = loadApi({
        headToken: 'current-head-token',
        authToken: 'current-auth-token',
        legacyToken: 'stale-legacy-token',
    })
    await api.createPerson({ givenName: 'Alex', familyName: 'Beispiel' })

    assert.equal(requests.length, 1)
    assert.equal(requests[0].options.headers.requesttoken, 'current-auth-token')
    assert.equal(requests[0].options.headers['X-Requested-With'], 'XMLHttpRequest')
}

{
    const { api, requests } = loadApi({
        headToken: undefined,
        authToken: 'current-auth-token',
        legacyToken: 'stale-legacy-token',
    })
    await api.createPerson({ givenName: 'Alex', familyName: 'Beispiel' })

    assert.equal(requests[0].options.headers.requesttoken, 'current-auth-token')
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
