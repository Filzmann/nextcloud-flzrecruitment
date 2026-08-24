(function (root) {
    'use strict'

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
            status.textContent = 'Einstellung wird gespeichert …'
            try {
                const result = await save(Number(form.dataset.revision || 0))
                form.dataset.revision = String(result.revision)
                status.textContent = 'Einstellung wurde gespeichert.'
            } catch (error) {
                status.textContent = error?.message || 'Die Einstellung konnte nicht gespeichert werden.'
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
    bind('recr-admin-first-guide-form', 'recr-admin-first-guide-submit', 'recr-admin-first-guide-status', (revision) => api.saveFirstGuideGroup(
        control('recr-admin-first-guide-group').value,
        revision,
    ))
}(window))
