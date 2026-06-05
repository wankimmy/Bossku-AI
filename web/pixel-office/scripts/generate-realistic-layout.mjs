/**
 * Writes public/assets/default-layout.json from realistic-office-layout.json.
 */
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const assetsDir = join(__dirname, '../public/assets')
const source = join(assetsDir, 'realistic-office-layout.json')
const fallback = join(assetsDir, 'default-layout.json')
const defaultOut = join(assetsDir, 'default-layout.json')
const webOut = join(__dirname, '../../public/pixel-office/assets/default-layout.json')

let layoutPath = source
if (!existsSync(layoutPath)) {
  if (!existsSync(fallback)) {
    console.error(
      `[generate-realistic-layout] Missing ${source} and ${fallback}.`,
    )
    process.exit(1)
  }
  console.warn(
    `[generate-realistic-layout] ${source} not found; using ${fallback}.`,
  )
  layoutPath = fallback
}

const layout = JSON.parse(readFileSync(layoutPath, 'utf8'))
const json = `${JSON.stringify(layout, null, 2)}\n`
writeFileSync(defaultOut, json, 'utf8')
mkdirSync(dirname(webOut), { recursive: true })
writeFileSync(webOut, json, 'utf8')
console.log(`Wrote ${defaultOut} and ${webOut} (${layout.furniture?.length ?? 0} furniture)`)
