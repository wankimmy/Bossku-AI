import { readFile } from 'node:fs/promises'
import { resolve } from 'node:path'

const required = [
  '01-dashboard-empty.png',
  '02-dashboard-typed.png',
  '03-agents.png',
  '04-plan.png',
  '05-changes.png',
  '06-audit.png',
  '07-memory.png',
  '08-approval.png',
  '09-skills-graph.png',
  '10-memory-inspector.png',
  '11-model-settings.png',
]

const screens = resolve(import.meta.dirname, '../assets/screens')
const pngSignature = '89504e470d0a1a0a'

for (const file of required) {
  const buffer = await readFile(resolve(screens, file))
  const signature = buffer.subarray(0, 8).toString('hex')
  const width = buffer.readUInt32BE(16)
  const height = buffer.readUInt32BE(20)

  if (signature !== pngSignature || width !== 1280 || height !== 800) {
    throw new Error(`${file} must be a 1280x800 PNG product capture.`)
  }
}

console.log(`Verified ${required.length} BosskuAI product captures.`)
