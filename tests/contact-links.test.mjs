import assert from 'node:assert/strict'
import vm from 'node:vm'
import { readFileSync } from 'node:fs'

const context = { window: {} }
vm.createContext(context)
vm.runInContext(readFileSync(new URL('../js/modules/contact-links.js', import.meta.url), 'utf8'), context)
const links = context.window.ADRecruitmentContactLinks

assert.equal(links.emailHref('alex.beispiel+job@example.invalid'), 'mailto:alex.beispiel+job@example.invalid')
assert.deepEqual(
    JSON.parse(JSON.stringify(links.emailLinkAttributes('alex.beispiel+job@example.invalid'))),
    { href: 'mailto:alex.beispiel+job@example.invalid', target: '_blank', rel: 'noopener noreferrer' },
)
assert.equal(links.emailLinkAttributes('ungültig'), null)
assert.equal(links.emailHref('alex@example.invalid\r\nBcc:test@example.invalid'), '')
assert.equal(links.phoneHref('+49 (0)30 / 555 01-01'), 'tel:+49305550101')
assert.equal(links.phoneHref('030 5550102'), 'tel:0305550102')
assert.equal(links.phoneHref('Telefon unbekannt'), '')

console.log('AD Recruitment contact-link tests passed')
