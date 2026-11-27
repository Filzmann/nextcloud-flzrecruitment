(function (root, factory) {
    'use strict'
    const translate = typeof t === 'function' ? t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)

    const api = factory()
    if (typeof module === 'object' && module.exports) {
        module.exports = api
    }
    if (root) {
        root.RecruitmentUiData = api
    }
}(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict'
    const translate = typeof t === 'function' ? t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)

    const labels = {
        received: translate('flzrecruitment', 'Eingegangen'),
        screening: translate('flzrecruitment', 'Vorprüfung'),
        questionnaire_pending: translate('flzrecruitment', 'Kurzfragebogen ausstehend'),
        questionnaire_received: translate('flzrecruitment', 'Kurzfragebogen eingegangen'),
        phone_planned: translate('flzrecruitment', 'Telefoninterview geplant'),
        phone_completed: translate('flzrecruitment', 'Telefoninterview abgeschlossen'),
        live_planned: translate('flzrecruitment', 'Liveinterview geplant'),
        decision_pending: translate('flzrecruitment', 'Entscheidung ausstehend'),
        basis_qualification: translate('flzrecruitment', 'Basisqualifikation'),
        approved_for_hire: translate('flzrecruitment', 'Zur Einstellung freigegeben'),
        accepted: translate('flzrecruitment', 'Zusage'),
        rejected: translate('flzrecruitment', 'Absage'),
        withdrawn: translate('flzrecruitment', 'Zurückgezogen'),
        hired: translate('flzrecruitment', 'Eingestellt'),
        archived: translate('flzrecruitment', 'Archiviert'),
        not_started: translate('flzrecruitment', 'Nicht begonnen'),
        in_progress: translate('flzrecruitment', 'In Bearbeitung'),
        completed: translate('flzrecruitment', 'Abgeschlossen'),
        aborted: translate('flzrecruitment', 'Abgebrochen'),
    }
    const sourceLabels = {
        email_import: 'E-Mail-Eingang',
        manual: 'Manuelle Ausnahme',
        referral: translate('flzrecruitment', 'Empfehlung'),
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
        if (minimum === null || minimum === undefined || minimum === '') return translate('flzrecruitment', 'Nicht angegeben')
        if (maximum === null || maximum === undefined || maximum === '') return `ca. ${minimum} Stunden`
        return `ca. ${minimum}–${maximum} Stunden`
    }

    return { csvList, desiredHoursLabel, sourceLabel, statusLabel }
}))
