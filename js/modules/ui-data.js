(function (root, factory) {
    'use strict'

    const api = factory()
    if (typeof module === 'object' && module.exports) {
        module.exports = api
    }
    if (root) {
        root.RecruitmentUiData = api
    }
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict'

    const labels = {
        received: 'Eingegangen',
        screening: 'Vorprüfung',
        questionnaire_pending: 'Kurzfragebogen ausstehend',
        questionnaire_received: 'Kurzfragebogen eingegangen',
        phone_planned: 'Telefoninterview geplant',
        phone_completed: 'Telefoninterview abgeschlossen',
        live_planned: 'Liveinterview geplant',
        decision_pending: 'Entscheidung ausstehend',
        accepted: 'Zusage',
        rejected: 'Absage',
        withdrawn: 'Zurückgezogen',
        hired: 'Eingestellt',
        archived: 'Archiviert',
        not_started: 'Nicht begonnen',
        in_progress: 'In Bearbeitung',
        completed: 'Abgeschlossen',
        aborted: 'Abgebrochen',
    }

    function csvList(value) {
        return [...new Set(String(value ?? '')
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean))]
    }

    function statusLabel(status) {
        return labels[status] ?? status
    }

    return { csvList, statusLabel }
}))
