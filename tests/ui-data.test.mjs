import assert from 'node:assert/strict'
import { createRequire } from 'node:module'

const require = createRequire(import.meta.url)
const { csvList, statusLabel } = require('../js/modules/ui-data.js')

assert.deepEqual(csvList(' team-a, team-b, team-a ,, '), ['team-a', 'team-b'])
assert.equal(statusLabel('questionnaire_pending'), 'Kurzfragebogen ausstehend')
assert.equal(statusLabel('unknown_state'), 'unknown_state')
