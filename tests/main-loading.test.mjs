import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const sourceUrl = new URL('../js/main.js', import.meta.url)
const source = readFileSync(sourceUrl, 'utf8')

class FakeElement {
    constructor() {
        this.attributes = new Map()
        this.children = []
        this.dataset = {}
        this.hidden = true
        this.textContent = ''
    }

    append(...children) {
        this.children.push(...children)
    }

    replaceChildren(...children) {
        this.children = children
    }

    setAttribute(name, value) {
        this.attributes.set(name, String(value))
    }

    removeAttribute(name) {
        this.attributes.delete(name)
    }

    hasAttribute(name) {
        return this.attributes.has(name)
    }
}

const content = new FakeElement()
const tabs = new FakeElement()
const status = new FakeElement()
const errorBox = new FakeElement()
const elements = {
    'adrecruitment-content': content,
    'adrecruitment-tabs': tabs,
    'adrecruitment-status': status,
    'adrecruitment-error': errorBox,
}
const document = {
    createElement: () => new FakeElement(),
    getElementById: (id) => elements[id] ?? null,
}
const window = {
    document,
    RecruitmentApi: {
        bootstrap: async () => {
            const error = new Error('Nicht erreichbar')
            error.status = 503
            throw error
        },
    },
    RecruitmentBubbleText: { appendBubbleText: () => '' },
    RecruitmentUiData: { csvList: () => [], sourceLabel: (value) => value, statusLabel: (value) => value },
    RecruitmentDialogOverlay: { createController: () => ({ open() {}, close() {} }) },
    RecruitmentApplicationWorkbench: { filterApplications: () => [], groupApplicationsByStatus: () => ({}) },
    RecruitmentSettingsNavigation: { sections: () => [], resolveActiveSection: () => '' },
}
window.window = window

vm.runInContext(source, vm.createContext({
    window,
    document,
    console,
    Error,
    FormData,
}), { filename: fileURLToPath(sourceUrl) })

await new Promise((resolve) => setImmediate(resolve))

assert.equal(content.hasAttribute('aria-busy'), false, 'Ein fehlgeschlagener Ladevorgang darf nicht dauerhaft als beschäftigt markiert bleiben.')
assert.equal(status.textContent, 'Fehler')
assert.equal(errorBox.hidden, false)
assert.equal(errorBox.textContent, 'Nicht erreichbar')
