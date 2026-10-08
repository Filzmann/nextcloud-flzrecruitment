import assert from 'node:assert/strict'

globalThis.window = globalThis
await import('../js/modules/settings-navigation.js')

const { sections, resolveActiveSection } = globalThis.RecruitmentSettingsNavigation

assert.deepEqual(sections({
    manage_catalog: true,
    interview: true,
    manage_mail_templates: true,
    manage_delegations: true,
    manage_candidate_pool: true,
    manage_extraction_settings: true,
}), [
    { id: 'templates', label: 'Interviewfragen' },
    { id: 'mail-templates', label: 'Mailvorlagen' },
    { id: 'permissions', label: 'Berechtigungen' },
    { id: 'candidate-pool', label: 'Datenschutz & Rückstellungen' },
])

assert.deepEqual(sections({ interview: true }), [
    { id: 'templates', label: 'Interviewfragen' },
])
assert.deepEqual(sections({ manage_mail_templates: true }), [
    { id: 'mail-templates', label: 'Mailvorlagen' },
])
assert.deepEqual(sections({ manage_delegations: true }), [
    { id: 'permissions', label: 'Berechtigungen' },
])
assert.deepEqual(sections({ manage_candidate_pool: true }), [
    { id: 'candidate-pool', label: 'Datenschutz & Rückstellungen' },
])
assert.deepEqual(sections({ manage_extraction_settings: true }), [])
assert.deepEqual(sections({}), [])

const mailOnly = sections({ manage_mail_templates: true })
assert.equal(resolveActiveSection('permissions', mailOnly), 'mail-templates')
assert.equal(resolveActiveSection('mail-templates', mailOnly), 'mail-templates')
assert.equal(resolveActiveSection('', []), '')
