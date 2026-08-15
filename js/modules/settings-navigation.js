(function (root) {
    'use strict'

    function sections(capabilities = {}) {
        return [
            ...(capabilities.manage_catalog || capabilities.interview
                ? [{ id: 'templates', label: 'Interviewfragen' }]
                : []),
            ...(capabilities.manage_mail_templates
                ? [{ id: 'mail-templates', label: 'Mailvorlagen' }]
                : []),
            ...(capabilities.manage_delegations
                ? [{ id: 'permissions', label: 'Berechtigungen' }]
                : []),
            ...(capabilities.manage_candidate_pool
                ? [{ id: 'candidate-pool', label: 'Datenschutz & Rückstellungen' }]
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
