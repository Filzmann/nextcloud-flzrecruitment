(function (root) {
    'use strict'
    const translate = typeof t === 'function' ? t : (_app, text, params) => Object.entries(params || {}).reduce((s, [k, v]) => s.split('{' + k + '}').join(String(v)), text)

    function sections(capabilities = {}) {
        return [
            ...(capabilities.manage_catalog || capabilities.interview
                ? [{ id: 'templates', label: translate('flzrecruitment', 'Interviewfragen') }]
                : []),
            ...(capabilities.manage_mail_templates
                ? [{ id: 'mail-templates', label: translate('flzrecruitment', 'Mailvorlagen') }]
                : []),
            ...(capabilities.manage_delegations
                ? [{ id: 'permissions', label: translate('flzrecruitment', 'Berechtigungen') }]
                : []),
            ...(capabilities.manage_candidate_pool
                ? [{ id: 'candidate-pool', label: translate('flzrecruitment', 'Datenschutz & Rückstellungen') }]
                : []),
        ]
    }

    function resolveActiveSection(activeSection, availableSections) {
        return availableSections.some(({ id }) => id === activeSection)
            ? activeSection
            : (availableSections[0]?.id || '')
    }

    root.RecruitmentSettingsNavigation = { sections, resolveActiveSection }
}(window))
