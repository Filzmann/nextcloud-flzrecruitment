import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import vm from 'node:vm'

const sourceUrl = new URL('../js/modules/pdf-lightbox.js', import.meta.url)
const source = readFileSync(sourceUrl, 'utf8')
const window = {}
window.window = window
vm.runInContext(source, vm.createContext({ window, console, Number, Math, Object, Array, Error }), { filename: fileURLToPath(sourceUrl) })

const viewer = window.RecruitmentPdfLightbox
assert.ok(viewer, 'PDF lightbox module is not exported')
assert.equal(
    JSON.stringify(viewer.normalizeRectangles([{ left: 20, top: 10, width: 40, height: 20 }], { left: 10, top: 0, width: 100, height: 100 })),
    JSON.stringify([{ x: 0.1, y: 0.1, width: 0.4, height: 0.2 }]),
)
assert.throws(
    () => viewer.normalizeRectangles([{ left: 0, top: 0, width: 0, height: 20 }], { left: 0, top: 0, width: 100, height: 100 }),
    /Markierung/,
)
assert.equal(typeof viewer.open, 'function')

class FakeClassList {
    constructor(element) { this.element = element }
    values() { return new Set(this.element.className.split(/\s+/).filter(Boolean)) }
    write(values) { this.element.className = [...values].join(' ') }
    add(...names) { const values = this.values(); names.forEach((name) => values.add(name)); this.write(values) }
    remove(...names) { const values = this.values(); names.forEach((name) => values.delete(name)); this.write(values) }
    toggle(name, force) {
        const values = this.values()
        const enabled = force === undefined ? !values.has(name) : force
        if (enabled) values.add(name); else values.delete(name)
        this.write(values)
        return enabled
    }
    contains(name) { return this.values().has(name) }
}

class FakeStyle {
    setProperty(name, value) { this[name] = String(value) }
}

class FakeElement {
    constructor(document, tag = 'div') {
        this.ownerDocument = document
        this.tagName = tag.toUpperCase()
        this.nodeType = 1
        this.parentElement = null
        this.children = []
        this.dataset = {}
        this.attributes = new Map()
        this.listeners = {}
        this.style = new FakeStyle()
        this.className = ''
        this.classList = new FakeClassList(this)
        this.textContent = ''
        this.removed = false
    }
    append(...children) { for (const child of children) { child.parentElement = this; this.children.push(child) } }
    replaceChildren(...children) { this.children.forEach((child) => { child.parentElement = null }); this.children = []; this.append(...children) }
    setAttribute(name, value) { this.attributes.set(name, String(value)) }
    getAttribute(name) { return this.attributes.get(name) ?? null }
    addEventListener(name, listener) { (this.listeners[name] ??= []).push(listener) }
    dispatch(name, event = {}) { for (const listener of this.listeners[name] || []) listener({ target: this, ...event }) }
    contains(candidate) { return candidate === this || this.children.some((child) => child.contains(candidate)) }
    closest(selector) {
        const className = selector.startsWith('.') ? selector.slice(1) : ''
        if (className && this.classList.contains(className)) return this
        return this.parentElement?.closest(selector) || null
    }
    focus() { this.ownerDocument.activeElement = this }
    showModal() { this.open = true }
    close() { this.open = false; this.dispatch('close') }
    remove() {
        if (this.parentElement) this.parentElement.children = this.parentElement.children.filter((child) => child !== this)
        this.parentElement = null
        this.removed = true
    }
    getContext() { return {} }
    getBoundingClientRect() {
        const parent = this.parentElement?.getBoundingClientRect?.() || { left: 0, top: 0 }
        const number = (value, fallback = 0) => Number.parseFloat(value ?? '') || fallback
        const width = number(this.style.width, this.classList.contains('flzrecruitment-pdf-page') ? 125 : 0)
        const height = number(this.style.height, this.classList.contains('flzrecruitment-pdf-page') ? 250 : 0)
        const left = parent.left + number(this.style.left)
        const top = parent.top + number(this.style.top)
        return { left, top, width, height, right: left + width, bottom: top + height }
    }
}

class FakeDocument {
    constructor() {
        this.body = new FakeElement(this, 'body')
        this.activeElement = new FakeElement(this, 'button')
    }
    createElement(tag) { return new FakeElement(this, tag) }
}

function all(root) { return [root, ...root.children.flatMap(all)] }

const fakeDocument = new FakeDocument()
const selections = []
const loadOptions = []
let destroyed = false
window.document = fakeDocument
window.devicePixelRatio = 1
window.OC = { linkTo: (_app, path) => `/apps/flzrecruitment/${path}` }
window.getSelection = () => null
const fakePdfjs = {
    TextLayer: class {
        constructor({ container }) { this.container = container }
        async render() { this.container.append(fakeDocument.createElement('span')) }
    },
    getDocument: (options) => {
        loadOptions.push(options)
        return {
            destroy() { destroyed = true },
            promise: Promise.resolve({
                numPages: 1,
                destroy() { destroyed = true },
                getPage: async () => ({
                    getViewport: ({ scale }) => ({ width: 100 * scale, height: 200 * scale }),
                    render: () => ({ promise: Promise.resolve() }),
                    getTextContent: async () => ({ items: [] }),
                }),
            }),
        }
    },
}
const sidePanel = fakeDocument.createElement('aside')
const handle = await viewer.open({
    url: '/document.pdf',
    title: 'Lebenslauf.pdf',
    sidePanel,
    fieldLinks: [{ pageNumber: 1, targetField: 'previousExperience', appliedValue: 'Assistenz', rectangles: [{ x: 0.1, y: 0.1, width: 0.2, height: 0.1 }] }],
    onSelection: (selection) => selections.push(selection),
    pdfjs: fakePdfjs,
})
await handle.ready
assert.equal(loadOptions[0].url, '/document.pdf')
assert.equal(loadOptions[0].withCredentials, true)
const dialogElements = all(handle.dialog)
const page = dialogElements.find((element) => element.classList.contains('flzrecruitment-pdf-page'))
const pages = dialogElements.find((element) => element.classList.contains('flzrecruitment-pdf-pages'))
assert.ok(page)
assert.equal(dialogElements.filter((element) => element.classList.contains('flzrecruitment-pdf-stored-mark')).length, 1)

const textSpan = all(page).find((element) => element.tagName === 'SPAN' && element.parentElement?.classList.contains('flzrecruitment-pdf-text-layer'))
window.getSelection = () => ({
    rangeCount: 1,
    isCollapsed: false,
    toString: () => 'Zwei Jahre Assistenz',
    getRangeAt: () => ({
        startContainer: textSpan,
        endContainer: textSpan,
        getClientRects: () => [{ left: 10, top: 20, width: 30, height: 10 }],
    }),
})
pages.dispatch('mouseup')
dialogElements.find((element) => element.textContent === 'Textauswahl übernehmen').dispatch('click')
assert.equal(selections[0].selectedText, 'Zwei Jahre Assistenz')
assert.equal(selections[0].pageNumber, 1)

dialogElements.find((element) => element.textContent === 'Bereich markieren').dispatch('click')
pages.dispatch('pointerdown', { target: page, clientX: 15, clientY: 25, pointerId: 1, preventDefault() {} })
pages.dispatch('pointermove', { clientX: 55, clientY: 65 })
pages.dispatch('pointerup', { clientX: 55, clientY: 65 })
assert.equal(selections[1].selectedText, '')
assert.equal(selections[1].rectangles.length, 1)

handle.close()
assert.equal(handle.dialog.removed, true)
assert.equal(destroyed, true)
