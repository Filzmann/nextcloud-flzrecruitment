import { readFileSync } from 'node:fs'
import { runInNewContext } from 'node:vm'

const source = readFileSync(new URL('../js/admin.js', import.meta.url), 'utf8')
const element = (value = '') => ({
    value, checked: false, disabled: false, textContent: '', dataset: {}, listeners: {},
    addEventListener(type, callback) { this.listeners[type] = callback },
})
const ids = [
    'recr-admin-mail-form', 'recr-admin-mail-submit', 'recr-admin-mail-status', 'recr-admin-mail-test-mode', 'recr-admin-mail-test-recipient',
    'recr-admin-extraction-form', 'recr-admin-extraction-submit', 'recr-admin-extraction-status', 'recr-admin-extraction-method',
    'recr-admin-first-guide-form', 'recr-admin-first-guide-submit', 'recr-admin-first-guide-status', 'recr-admin-first-guide-group',
]
const elements = Object.fromEntries(ids.map((id) => [id, element()]))
elements['recr-admin-mail-form'].dataset.revision = '2'
elements['recr-admin-mail-test-mode'].checked = true
elements['recr-admin-mail-test-recipient'].value = 'test@example.invalid'
elements['recr-admin-extraction-form'].dataset.revision = '4'
elements['recr-admin-extraction-method'].value = 'rules'
elements['recr-admin-first-guide-form'].dataset.revision = '5'
elements['recr-admin-first-guide-group'].value = 'ad-erstbegleitung'

const calls = []
const api = {
    saveMailSettings: async (data) => { calls.push(['mail', data]); return { ...data, revision: 3 } },
    saveResumeExtractionSettings: async (data) => { calls.push(['extraction', data]); return { ...data, revision: 5 } },
    saveFirstGuideGroup: async (groupId, revision) => { calls.push(['group', { groupId, revision }]); return { revision: 6 } },
}
runInNewContext(source, {
    window: { RecruitmentApi: api },
    document: { getElementById: (id) => elements[id] || null },
})

for (const id of ['recr-admin-mail-form', 'recr-admin-extraction-form', 'recr-admin-first-guide-form']) {
    await elements[id].listeners.submit({ preventDefault() {} })
}

const expected = [
    ['mail', { testMode: true, testRecipient: 'test@example.invalid', revision: 2 }],
    ['extraction', { method: 'rules', revision: 4 }],
    ['group', { groupId: 'ad-erstbegleitung', revision: 5 }],
]
if (JSON.stringify(calls) !== JSON.stringify(expected)) throw new Error(`Adminspeicherpfade stimmen nicht: ${JSON.stringify(calls)}`)
if (elements['recr-admin-mail-form'].dataset.revision !== '3'
    || elements['recr-admin-extraction-form'].dataset.revision !== '5'
    || elements['recr-admin-first-guide-form'].dataset.revision !== '6') {
    throw new Error('Adminformulare übernehmen die serverseitigen Revisionen nicht.')
}
for (const id of ['recr-admin-mail-status', 'recr-admin-extraction-status', 'recr-admin-first-guide-status']) {
    if (!elements[id].textContent.includes('gespeichert')) throw new Error(`Erfolgsmeldung fehlt: ${id}`)
}

console.log('AD Recruitment admin settings tests passed')
