import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const sourceUrl = new URL('../js/main.js', import.meta.url)
const source = readFileSync(sourceUrl, 'utf8')

class FakeElement {
    constructor(tag = 'div') {
        this.tagName = tag.toUpperCase()
        this.attributes = new Map()
        this.children = []
        this.dataset = {}
        this.listeners = {}
        this.hidden = false
        this.textContent = ''
        this.className = ''
        this.name = ''
        this.value = ''
        this.type = ''
        this.required = false
        this.selected = false
        this.disabled = false
        this.tabIndex = 0
    }
    append(...children) { this.children.push(...children) }
    replaceChildren(...children) { this.children = children }
    setAttribute(name, value) { this.attributes.set(name, String(value)) }
    removeAttribute(name) { this.attributes.delete(name) }
    hasAttribute(name) { return this.attributes.has(name) }
    addEventListener(name, listener) { (this.listeners[name] ??= []).push(listener) }
    dispatch(name, event = {}) { return this.listeners[name]?.[0]?.(event) }
    focus() {}
    get childElementCount() { return this.children.length }
    get firstElementChild() { return this.children[0] ?? null }
}

function descendants(node) {
    return [node, ...node.children.flatMap((child) => child instanceof FakeElement ? descendants(child) : [])]
}

function findByText(root, text) {
    return descendants(root).find((node) => node.textContent === text)
}

async function waitFor(predicate, message) {
    for (let attempt = 0; attempt < 50; attempt++) {
        if (predicate()) return
        await new Promise((resolve) => setImmediate(resolve))
    }
    throw new Error(message)
}

class FakeFormData {
    constructor(form) {
        this.values = new Map()
        for (const control of descendants(form)) {
            if (!control.name) continue
            if (control.type === 'checkbox' && !control.checked) continue
            if (control.tagName === 'SELECT') {
                const selected = control.children.find((option) => option.selected) ?? control.children[0]
                this.values.set(control.name, selected?.value ?? '')
            } else {
                this.values.set(control.name, control.value)
            }
        }
    }
    get(name) { return this.values.get(name) ?? null }
    getAll(name) { return this.values.has(name) ? [this.values.get(name)] : [] }
    has(name) { return this.values.has(name) }
}

const content = new FakeElement('main')
const tabs = new FakeElement('nav')
const status = new FakeElement('span')
const errorBox = new FakeElement('div')
const elements = {
    'adrecruitment-content': content,
    'adrecruitment-tabs': tabs,
    'adrecruitment-status': status,
    'adrecruitment-error': errorBox,
}
const document = {
    createElement: (tag) => new FakeElement(tag),
    getElementById: (id) => elements[id] ?? null,
}

const baseMessage = {
    mailbox: { label: 'Website-Bewerbungen' },
    senderAddress: 'alex@example.invalid',
    receivedAt: '2026-08-02 09:00:00',
    state: 'new',
    version: 1,
    bodyText: 'Name: Alex Beispiel',
    fieldSuggestions: {
        name: { value: 'Alex Beispiel' },
        email: { value: 'alex@example.invalid' },
        previousExperience: { value: 'Zwei Jahre Assistenz' },
    },
    attachments: [{
        id: 31,
        originalName: 'Bewerbung.pdf',
        sizeBytes: 2048,
        comments: [{ id: 1, kind: 'free', body: 'Freier Hinweis', actorUid: 'hr-user', createdAt: '2026-08-02 11:00:00' }],
    }],
}
const messages = [
    { ...baseMessage, id: 21, subject: 'Bewerbung Assistenz' },
    { ...baseMessage, id: 22, subject: 'Bereits zugeordnet', applicationId: 7, state: 'assigned' },
]
const calls = []
const api = {
    bootstrap: async () => ({
        data: { jobs: [], people: [], applications: [{ id: 7, status: 'received' }], templates: [], applicationStatuses: [] },
        capabilities: { manage_unassigned_inbox: true, manage_documents: true, edit_applications: true },
        areas: [],
    }),
    inbox: async () => ({ messages }),
    inboxMessage: async (id) => messages.find((message) => message.id === id),
    assignInboxMessage: async (...args) => { calls.push(['assign', ...args]); return {} },
    ignoreInboxMessage: async (...args) => { calls.push(['ignore', ...args]); return {} },
    documentUrl: (id) => `/apps/adrecruitment/api/attachments/${id}/document`,
    attachmentFieldContext: async (...args) => { calls.push(['field-context', ...args]); return { targetField: args[1], value: '', version: 3 } },
    createAttachmentFieldLink: async (...args) => { calls.push(['field-link', ...args]); return { id: 1 } },
}
const window = {
    document,
    RecruitmentApi: api,
    RecruitmentBubbleText: { appendBubbleText: () => '' },
    RecruitmentUiData: { csvList: () => [], desiredHoursLabel: () => '', sourceLabel: (value) => value, statusLabel: (value) => value },
    RecruitmentDialogOverlay: { createController: () => ({ open() {}, close() {} }) },
    RecruitmentApplicationWorkbench: { canMoveApplication: () => false, filterApplications: () => [], groupApplicationsByStatus: () => ({}) },
    RecruitmentSettingsNavigation: { sections: () => [], resolveActiveSection: () => '' },
    RecruitmentPdfLightbox: { open: async (options) => { calls.push(['lightbox', options]); return { close() {} } } },
}
window.window = window

vm.runInContext(source, vm.createContext({
    window, document, console, Error, FormData: FakeFormData, Number, Object, Array, Math,
}), { filename: fileURLToPath(sourceUrl) })

await new Promise((resolve) => setImmediate(resolve))
assert.equal(status.textContent, 'Bereit')
assert.ok(findByText(content, 'Bewerbung Assistenz'))

await findByText(content, 'Nachricht prüfen').dispatch('click')
assert.ok(findByText(content, 'Unveränderter Mailtext'))
assert.ok(findByText(content, 'Bewerbung.pdf · 2 KB · PDF'))
assert.ok(findByText(content, 'Freier Hinweis · hr-user · 2026-08-02 11:00:00'))
findByText(content, 'PDF in Lightbox öffnen').dispatch('click')
await waitFor(() => calls.some(([action]) => action === 'lightbox'), 'PDF lightbox was not opened')
assert.equal(calls.find(([action]) => action === 'lightbox')[1].url, '/apps/adrecruitment/api/attachments/31/document')
assert.equal(descendants(content).some((node) => node.tagName === 'IFRAME'), false)

const assignmentForm = descendants(content).find((node) => node.tagName === 'FORM' && findByText(node, 'Bewerbung zuordnen'))
const emailAcceptance = descendants(assignmentForm).find((node) => node.name === 'accept-email')
const emailSuggestion = descendants(assignmentForm).find((node) => node.name === 'suggestion-email')
const experienceAcceptance = descendants(assignmentForm).find((node) => node.name === 'accept-previousExperience')
const experienceSuggestion = descendants(assignmentForm).find((node) => node.name === 'suggestion-previousExperience')
emailAcceptance.checked = true
emailSuggestion.value = 'korrigiert@example.invalid'
experienceAcceptance.checked = true
experienceSuggestion.value = 'Drei Jahre Assistenz'
assignmentForm.dispatch('submit', { preventDefault() {} })
await waitFor(() => calls.some(([action]) => action === 'assign'), 'Inbox assignment request was not sent')
assert.deepEqual(calls.find(([action]) => action === 'assign'), ['assign', 21, 7, 1, {
    email: 'korrigiert@example.invalid',
    previousExperience: 'Drei Jahre Assistenz',
}])

await waitFor(() => findByText(content, 'Bereits zugeordnet'), 'Inbox list was not restored after assignment')
const assignedCard = descendants(content).find((node) => node.className.includes('adrecruitment-card') && findByText(node, 'Bereits zugeordnet'))
findByText(assignedCard, 'Nachricht prüfen').dispatch('click')
await waitFor(() => findByText(content, 'PDF in Lightbox öffnen'), 'Assigned inbox detail was not opened')
findByText(content, 'PDF in Lightbox öffnen').dispatch('click')
await waitFor(() => calls.filter(([action]) => action === 'lightbox').length === 2, 'Assigned PDF lightbox was not opened')
const assignedLightbox = calls.filter(([action]) => action === 'lightbox')[1][1]
assignedLightbox.onSelection({
    pageNumber: 1,
    selectedText: 'Zwei Jahre Assistenz',
    rectangles: [{ x: 0.1, y: 0.2, width: 0.3, height: 0.04 }],
})
await waitFor(() => findByText(assignedLightbox.sidePanel, 'Zielfeld ist bereit.'), 'Applicant field context was not loaded')
const fieldLinkForm = descendants(assignedLightbox.sidePanel).find((node) => node.tagName === 'FORM')
fieldLinkForm.dispatch('submit', { preventDefault() {} })
await waitFor(() => calls.some(([action]) => action === 'field-link'), 'PDF field-link request was not sent')
const fieldLinkPayload = calls.find(([action]) => action === 'field-link')[2]
assert.equal(fieldLinkPayload.targetField, 'previousExperience')
assert.equal(fieldLinkPayload.appliedValue, 'Zwei Jahre Assistenz')
assert.equal(fieldLinkPayload.expectedVersion, 3)
