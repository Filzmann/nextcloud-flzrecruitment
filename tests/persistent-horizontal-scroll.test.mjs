import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const root = new URL('..', import.meta.url)
const css = readFileSync(new URL('../css/style.css', import.meta.url), 'utf8')
const script = readFileSync(new URL('../js/main.js', import.meta.url), 'utf8')

assert.match(css, /\.app-horizontal-scroll-proxy\s*\{[^}]*position:\s*sticky[^}]*bottom:\s*0[^}]*overflow-x:\s*auto/s)
assert.match(css, /\.app-horizontal-scroll-proxy\[hidden\]/)
assert.match(script, /className:\s*'app-horizontal-scroll-proxy'/)
assert.match(script, /ResizeObserver/)
assert.match(script, /proxy\.addEventListener\('scroll'/)
assert.match(script, /target\.addEventListener\('scroll'/)
assert.match(script, /result\.querySelector\('\.adrecruitment-board, \.adrecruitment-table-wrap'\)/)

console.log('AD Recruitment persistent horizontal scroll contract passed')
