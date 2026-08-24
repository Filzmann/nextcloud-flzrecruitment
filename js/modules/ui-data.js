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
        basis_qualification: 'Basisqualifikation',
        approved_for_hire: 'Zur Einstellung freigegeben',
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
    const sourceLabels = {
        email_import: 'E-Mail-Eingang',
        manual: 'Manuelle Ausnahme',
        referral: 'Empfehlung',
        other: 'Sonstiger Kanal',
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

    function sourceLabel(source) {
        return sourceLabels[source] ?? source
    }

    function desiredHoursLabel(minimum, maximum) {
        if (minimum === null || minimum === undefined || minimum === '') return 'Nicht angegeben'
        if (maximum === null || maximum === undefined || maximum === '') return `ca. ${minimum} Stunden`
        return `ca. ${minimum}–${maximum} Stunden`
    }

    return { csvList, desiredHoursLabel, sourceLabel, statusLabel }
}))
