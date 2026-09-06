import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { runInNewContext } from 'node:vm'

export function loadUmdModule(path) {
    const filename = path instanceof URL ? fileURLToPath(path) : path
    const module = { exports: {} }
    const context = { module, exports: module.exports }
    context.globalThis = context
    runInNewContext(readFileSync(filename, 'utf8'), context, { filename })
    return module.exports
}
