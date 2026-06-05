/**
 * Writes public/assets/default-layout.json from the startup office template.
 * Run: node scripts/generate-startup-layout.mjs
 */
import { writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

// Minimal inline copy of layout (generated via tsc build). Run after `npm run build` in pixel-office
// or use dynamic import from dist — for dev, we import from source using vite-node alternative.

const __dirname = dirname(fileURLToPath(import.meta.url))

async function main() {
  const { createStartupOfficeLayout } = await import('../src/office/layout/startupOfficeLayout.ts')
  const layout = createStartupOfficeLayout()
  const outPath = join(__dirname, '../public/assets/default-layout.json')
  writeFileSync(outPath, `${JSON.stringify(layout, null, 2)}\n`, 'utf8')
  console.log(`Wrote ${outPath} (${layout.furniture.length} furniture items)`)
}

main().catch((err) => {
  console.error(err)
  process.exit(1)
})
