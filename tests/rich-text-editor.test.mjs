import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import vm from 'node:vm'

const source = readFileSync(new URL('../js/modules/rich-text-editor.js', import.meta.url), 'utf8')
const commands = []

class FakeElement {
    constructor(tag) {
        this.tagName = tag
        this.children = []
        this.listeners = {}
        this.attributes = new Map()
        this.innerHTML = ''
        this.textContent = ''
        this.className = ''
    }
    append(...children) { this.children.push(...children) }
    addEventListener(type, listener) { this.listeners[type] = listener }
    setAttribute(name, value) { this.attributes.set(name, String(value)) }
    focus() { this.focused = true }
}

const document = {
    createElement: (tag) => new FakeElement(tag),
    execCommand: (command, _ui, value) => { commands.push([command, value]); return true },
}
const window = { document, prompt: () => 'https://example.invalid/info' }
window.window = window
vm.runInContext(source, vm.createContext({ window, document }))

const editor = window.RecruitmentRichTextEditor.create({ name: 'body', html: '<p>Hallo</p>' })
assert.equal(editor.value(), '<p>Hallo</p>')
assert.equal(editor.element.children[1].attributes.get('role'), 'textbox')
assert.equal(editor.element.children[1].attributes.get('aria-multiline'), 'true')

const toolbar = editor.element.children[0]
toolbar.children[0].listeners.click({ preventDefault() {} })
assert.deepEqual(commands[0], ['bold', null])

const pasteEvent = {
    preventDefaultCalled: false,
    preventDefault() { this.preventDefaultCalled = true },
    clipboardData: { getData: () => '<script>nur Text</script>' },
}
editor.element.children[1].listeners.paste(pasteEvent)
assert.equal(pasteEvent.preventDefaultCalled, true)
assert.deepEqual(commands.at(-1), ['insertText', '<script>nur Text</script>'])
