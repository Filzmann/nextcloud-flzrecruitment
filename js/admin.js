(function (root) {
    'use strict'
    const translate = typeof t === 'function' ? t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)

    const api = root.RecruitmentApi

    function control(id) {
        return document.getElementById(id)
    }

    function bind(formId, submitId, statusId, save) {
        const form = control(formId)
        const submit = control(submitId)
        const status = control(statusId)
        if (!form || !submit || !status || !api) return
        form.addEventListener('submit', async (event) => {
            event.preventDefault()
            submit.disabled = true
            status.textContent = translate('flzrecruitment', 'Einstellung wird gespeichert') + ' ' + '…'
            try {
                const result = await save(Number(form.dataset.revision || 0))
                form.dataset.revision = String(result.revision)
                status.textContent = translate('flzrecruitment', 'Einstellung wurde gespeichert.')
            } catch (error) {
                status.textContent = error?.message || translate('flzrecruitment', 'Die Einstellung konnte nicht gespeichert werden.')
            } finally {
                submit.disabled = false
            }
        })
    }

    bind('recr-admin-mail-form', 'recr-admin-mail-submit', 'recr-admin-mail-status', (revision) => api.saveMailSettings({
        testMode: control('recr-admin-mail-test-mode').checked,
        testRecipient: control('recr-admin-mail-test-recipient').value,
        revision,
    }))
    bind('recr-admin-extraction-form', 'recr-admin-extraction-submit', 'recr-admin-extraction-status', (revision) => api.saveResumeExtractionSettings({
        method: control('recr-admin-extraction-method').value,
        revision,
    }))
}(window))
