import assert from 'node:assert/strict'

globalThis.window = globalThis
await import('../js/modules/dialog-overlay.js')

const listeners = {}
const dialog = {
    open: true,
    closeCount: 0,
    showModal() { this.open = true },
    close() { this.closeCount++; this.open = false; listeners.close?.() },
    removeAttribute(name) { if (name === 'open') this.open = false },
    addEventListener(name, listener) { listeners[name] = listener },
}
const trigger = { focusCount: 0, focus() { this.focusCount++ } }
const controller = globalThis.RecruitmentDialogOverlay.createController(dialog, trigger)

assert.equal(dialog.open, false, 'A newly controlled creation dialog must start closed')
assert.equal(dialog.closeCount, 0, 'Initialisation must not dispatch a close event')
assert.equal(trigger.focusCount, 0, 'Initialisation must not steal focus')

controller.open()
assert.equal(dialog.open, true)
controller.close()
assert.equal(dialog.open, false)
assert.equal(trigger.focusCount, 1)

controller.open()
let prevented = false
listeners.cancel({ preventDefault() { prevented = true } })
assert.equal(prevented, true)
assert.equal(dialog.open, false)
assert.equal(trigger.focusCount, 2)
