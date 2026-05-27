/**
 * Writes public/assets/default-layout.json from realistic-office-layout.json.
 */
import { copyFileSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const assetsDir = join(__dirname, '../public/assets')
const source = join(assetsDir, 'realistic-office-layout.json')
const defaultOut = join(assetsDir, 'default-layout.json')
const webOut = join(__dirname, '../../public/pixel-office/assets/default-layout.json')

const layout = JSON.parse(readFileSync(source, 'utf8'))
const json = `${JSON.stringify(layout, null, 2)}\n`
writeFileSync(defaultOut, json, 'utf8')
writeFileSync(webOut, json, 'utf8')
console.log(`Wrote ${defaultOut} and ${webOut} (${layout.furniture?.length ?? 0} furniture)`)
