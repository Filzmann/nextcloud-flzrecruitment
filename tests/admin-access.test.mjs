import { readFileSync } from 'node:fs'
import { runInNewContext } from 'node:vm'
import { fileURLToPath } from 'node:url'

const overlayFilename = fileURLToPath(new URL('../js/shared/overlay.js', import.meta.url))
const overlaySource = readFileSync(overlayFilename, 'utf8')
const filename = fileURLToPath(new URL('../js/admin-access.js', import.meta.url))
const source = readFileSync(filename, 'utf8')
const template = readFileSync(new URL('../templates/index.php', import.meta.url), 'utf8')
const style = readFileSync(new URL('../css/style.css', import.meta.url), 'utf8')
const element = () => ({ children: [], dataset: {}, disabled: false, hidden: false, textContent: '', attributes: {}, focused: false, listeners: {}, append(child) { this.children.push(child) }, addEventListener(type, callback) { (this.listeners[type] = this.listeners[type] || []).push(callback) }, setAttribute(name, value) { this.attributes[name] = String(value) }, getAttribute(name) { return name in this.attributes ? this.attributes[name] : null }, focus() { this.focused = true }, replaceChildren() { this.children = [] } })
const form = element()
form.elements = { enabled: { checked: true } }
const history = element()
const status = element()
const overlay = element()
overlay.hidden = true
const trigger = element()
const closeButton = element()
const docListeners = {}
const calls = []
const api = {
    adminFullAccess: async () => { calls.push(['status']); return { history: [] } },
    activateAdminFullAccess: async (uid, minutes) => { calls.push(['activate', uid, minutes]); return {} },
    revokeAdminFullAccess: async (uid) => { calls.push(['revoke', uid]); return {} },
}
class FakeFormData {
    get(name) { return { enabled: 'on', targetUid: ' admin-target ', durationMinutes: '60' }[name] }
}
const context = {
    window: { RecruitmentApi: api }, FormData: FakeFormData, Date, Intl, JSON, String, Number, Promise, encodeURIComponent,
    document: {
        getElementById: (id) => ({ 'recr-full-access-form': form, 'recr-full-access-history': history, 'recr-full-access-status': status, 'recr-admin-access-overlay': overlay, 'recr-admin-grant-warning': trigger, 'recr-admin-access-overlay-close': closeButton }[id] || null),
        createElement: () => element(),
        addEventListener: (type, callback) => { (docListeners[type] = docListeners[type] || []).push(callback) },
        querySelectorAll: () => [],
        head: { appendChild() {} },
    },
}
runInNewContext(overlaySource, context, { filename: overlayFilename })
runInNewContext(source, context, { filename })
const fire = async (el, type, event) => { for (const callback of el.listeners[type] || []) await callback(event) }
await new Promise((resolve) => setImmediate(resolve))
if (calls.length !== 0) throw new Error('Historie wird vor dem Öffnen des Overlays geladen.')
await fire(trigger, 'click', { target: trigger })
if (overlay.hidden !== false || trigger.getAttribute('aria-expanded') !== 'true' || !closeButton.focused) throw new Error('Warn-Icon öffnet das Overlay nicht korrekt.')
if (JSON.stringify(calls) !== JSON.stringify([['status']])) throw new Error('Öffnen lädt die Historie nicht.')
await fire(form, 'submit', { preventDefault() {} })
const revokeButton = { dataset: { revokeUid: 'admin-target' }, disabled: false }
await fire(history, 'click', { target: { closest: () => revokeButton } })
if (JSON.stringify(calls) !== JSON.stringify([['status'], ['activate', 'admin-target', 60], ['status'], ['revoke', 'admin-target'], ['status']])) throw new Error(`Freigabesteuerung ruft falsche API-Pfade auf: ${JSON.stringify(calls)}`)
if (!status.textContent.includes('widerrufen')) throw new Error('Widerruf bestätigt seinen Status nicht.')
for (const callback of docListeners.keydown || []) await callback({ key: 'Escape' })
if (overlay.hidden !== true || trigger.getAttribute('aria-expanded') !== 'false' || !trigger.focused) throw new Error('Escape schließt das Overlay nicht korrekt.')
await fire(trigger, 'click', { target: trigger })
await fire(closeButton, 'click', {})
if (overlay.hidden !== true) throw new Error('Schließen-Button schließt nicht.')
await fire(trigger, 'click', { target: trigger })
await fire(overlay, 'click', { target: overlay })
if (overlay.hidden !== true) throw new Error('Backdrop-Klick schließt nicht.')
for (const contract of ['id="recr-admin-access-overlay"', 'role="dialog"', 'aria-modal="true"', 'id="recr-admin-grant-warning"', 'aria-haspopup="dialog"', 'aria-controls="recr-admin-access-overlay"', 'Datenschutzbeauftragte']) if (!template.includes(contract)) throw new Error(`Overlay für fehlenden Admin-Vollzugriff fehlt: ${contract}`)
if (template.includes('<details class="recr-admin-grant-warning"') || template.includes('href="#recr-full-access"')) throw new Error('Warn-Icon/Verweis ist noch als details-Element bzw. Anchor umgesetzt.')
const bundleOverlay = readFileSync(new URL('../js/shared/overlay.js', import.meta.url), 'utf8')
for (const contract of ['class="flz-shared-warn-trigger"', 'class="flz-shared-overlay"', "addScript('flzrecruitment', 'shared/overlay')"]) if (!template.includes(contract)) throw new Error(`Geteilte Overlay-Klassen/Registrierung fehlen in der Vorlage: ${contract}`)
if (!bundleOverlay.includes('.flz-shared-warn-trigger') || !bundleOverlay.includes('.flz-shared-overlay')) throw new Error('Warn-Icon und Overlay werden nicht mehr durch das geteilte Bundle gestaltet.')

console.log('Filzmann Recruitment admin access UI tests passed')
