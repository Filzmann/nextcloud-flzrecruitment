(function (root) {
    'use strict'

    function createController(dialog, trigger) {
        dialog.removeAttribute('open')
        const open = () => {
            if (typeof dialog.showModal === 'function') dialog.showModal()
            else dialog.setAttribute('open', '')
            dialog.querySelector?.('input:not([type="hidden"]), select, textarea, button')?.focus()
        }
        const close = () => {
            if (typeof dialog.close === 'function') dialog.close()
            else {
                dialog.removeAttribute('open')
                trigger.focus()
            }
        }
        dialog.addEventListener('close', () => trigger.focus())
        dialog.addEventListener('cancel', (event) => {
            event.preventDefault()
            close()
        })
        return { open, close }
    }

    root.RecruitmentDialogOverlay = { createController }
})(typeof window === 'undefined' ? globalThis : window)
