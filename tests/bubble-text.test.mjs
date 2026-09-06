import assert from 'node:assert/strict'
import { loadUmdModule } from './load-umd.mjs'

const { appendBubbleText } = loadUmdModule(new URL('../js/modules/bubble-text.js', import.meta.url))

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
