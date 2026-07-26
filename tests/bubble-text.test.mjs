import assert from 'node:assert/strict'
import { createRequire } from 'node:module'

const require = createRequire(import.meta.url)
const { appendBubbleText } = require('../js/modules/bubble-text.js')

assert.equal(appendBubbleText('', 'Zeitlich flexibel.'), 'Zeitlich flexibel.')
assert.equal(
    appendBubbleText('Erfahrung ist vorhanden.', 'Zeitlich flexibel.'),
    'Erfahrung ist vorhanden. Zeitlich flexibel.',
)
assert.equal(
    appendBubbleText('Erfahrung ist vorhanden', 'Zeitlich flexibel.'),
    'Erfahrung ist vorhanden. Zeitlich flexibel.',
)
assert.equal(
    appendBubbleText('Erste Zeile\n', 'Zweite Aussage.'),
    'Erste Zeile\nZweite Aussage.',
)
assert.equal(appendBubbleText('Vorhanden.', '   '), 'Vorhanden.')
