import assert from 'node:assert/strict'
import { loadUmdModule } from './load-umd.mjs'

const { csvList, desiredHoursLabel, sourceLabel, statusLabel } = loadUmdModule(
    new URL('../js/modules/ui-data.js', import.meta.url),
)

assert.deepEqual(Array.from(csvList(' team-a, team-b, team-a ,, ')), ['team-a', 'team-b'])
assert.equal(statusLabel('questionnaire_pending'), 'Kurzfragebogen ausstehend')
assert.equal(statusLabel('basis_qualification'), 'Basisqualifikation')
assert.equal(statusLabel('unknown_state'), 'unknown_state')
assert.equal(sourceLabel('email_import'), 'E-Mail-Eingang')
assert.equal(sourceLabel('manual'), 'Manuelle Ausnahme')
assert.equal(sourceLabel('referral'), 'Empfehlung')
assert.equal(desiredHoursLabel(null, null), 'Nicht angegeben')
assert.equal(desiredHoursLabel(20, null), 'ca. 20 Stunden')
assert.equal(desiredHoursLabel(20, 30), 'ca. 20–30 Stunden')
