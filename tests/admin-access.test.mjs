import { readFileSync } from 'node:fs'
import { runInNewContext } from 'node:vm'

const source = readFileSync(new URL('../js/admin-access.js', import.meta.url), 'utf8')
const element = () => ({ children: [], dataset: {}, disabled: false, textContent: '', listeners: {}, append(child) { this.children.push(child) }, addEventListener(type, callback) { this.listeners[type] = callback }, replaceChildren() { this.children = [] } })
const form = element()
form.elements = { enabled: { checked: true } }
const history = element()
const status = element()
const calls = []
const api = {
    adminFullAccess: async () => { calls.push(['status']); return { history: [] } },
    activateAdminFullAccess: async (uid, minutes) => { calls.push(['activate', uid, minutes]); return {} },
    revokeAdminFullAccess: async (uid) => { calls.push(['revoke', uid]); return {} },
}
class FakeFormData {
    get(name) { return { enabled: 'on', targetUid: ' admin-target ', durationMinutes: '60' }[name] }
}
runInNewContext(source, {
    window: { RecruitmentApi: api }, FormData: FakeFormData, Date,
    document: { getElementById: (id) => ({ 'recr-full-access-form': form, 'recr-full-access-history': history, 'recr-full-access-status': status }[id] || null), createElement: () => element() },
})
await new Promise((resolve) => setImmediate(resolve))
await form.listeners.submit({ preventDefault() {} })
const revokeButton = { dataset: { revokeUid: 'admin-target' }, disabled: false }
await history.listeners.click({ target: { closest: () => revokeButton } })
if (JSON.stringify(calls) !== JSON.stringify([['status'], ['activate', 'admin-target', 60], ['status'], ['revoke', 'admin-target'], ['status']])) throw new Error(`Freigabesteuerung ruft falsche API-Pfade auf: ${JSON.stringify(calls)}`)
if (!status.textContent.includes('widerrufen')) throw new Error('Widerruf bestätigt seinen Status nicht.')

console.log('AD Recruitment admin access UI tests passed')
