(function (root) {
    'use strict'

    const controls = [
        ['bold', 'Fett', 'B'],
        ['italic', 'Kursiv', 'I'],
        ['insertUnorderedList', 'Aufzählung', '• Liste'],
        ['insertOrderedList', 'Nummerierte Liste', '1. Liste'],
        ['createLink', 'Link einfügen', 'Link'],
        ['removeFormat', 'Formatierung entfernen', 'Format löschen'],
    ]

    function create({ name = 'body', html = '' } = {}) {
        const wrapper = root.document.createElement('div')
        wrapper.className = 'flzrecruitment-rte'
        const toolbar = root.document.createElement('div')
        toolbar.className = 'flzrecruitment-rte__toolbar'
        toolbar.setAttribute('role', 'toolbar')
        toolbar.setAttribute('aria-label', 'Text formatieren')
        const editable = root.document.createElement('div')
        editable.className = 'flzrecruitment-rte__content'
        editable.contentEditable = 'true'
        editable.innerHTML = html
        editable.setAttribute('role', 'textbox')
        editable.setAttribute('aria-multiline', 'true')
        editable.setAttribute('aria-label', 'HTML-Nachrichtentext')
        editable.setAttribute('data-name', name)

        const execute = (command, value = null) => {
            editable.focus()
            root.document.execCommand(command, false, value)
        }
        for (const [command, label, text] of controls) {
            const control = root.document.createElement('button')
            control.type = 'button'
            control.className = 'flzrecruitment-rte__button'
            control.textContent = text
            control.setAttribute('aria-label', label)
            control.addEventListener('click', (event) => {
                event.preventDefault()
                if (command !== 'createLink') {
                    execute(command)
                    return
                }
                const url = root.prompt?.('Linkziel (https://, http:// oder mailto:)', 'https://')
                if (typeof url === 'string' && url.trim() !== '') execute(command, url.trim())
            })
            toolbar.append(control)
        }
        editable.addEventListener('paste', (event) => {
            event.preventDefault()
            execute('insertText', event.clipboardData?.getData('text/plain') || '')
        })
        wrapper.append(toolbar, editable)
        return {
            element: wrapper,
            value: () => editable.innerHTML.trim(),
            setValue: (value) => { editable.innerHTML = value },
            insertText: (text) => execute('insertText', text),
            focus: () => editable.focus(),
        }
    }

    root.RecruitmentRichTextEditor = { create }
}(window))
