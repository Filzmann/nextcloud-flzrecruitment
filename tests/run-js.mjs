import { readdirSync } from 'node:fs'
import { spawnSync } from 'node:child_process'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const testsDirectory = dirname(fileURLToPath(import.meta.url))
const files = readdirSync(testsDirectory)
    .filter((file) => file.endsWith('.test.mjs'))
    .sort()

for (const file of files) {
    const result = spawnSync(process.execPath, [join(testsDirectory, file)], { stdio: 'inherit' })
    if (result.status !== 0) {
        process.exit(result.status ?? 1)
    }
}

console.log('Filzmann Recruitment JavaScript tests passed')
