import { spawnSync } from 'node:child_process'

const npm = process.platform === 'win32' ? 'npm.cmd' : 'npm'

function run(args) {
  const result = spawnSync(npm, args, {
    cwd: process.cwd(),
    env: process.env,
    shell: process.platform === 'win32',
    stdio: 'inherit',
  })

  if (result.error) {
    console.error(result.error.message)
    process.exit(1)
  }

  if (result.status !== 0) {
    process.exit(result.status ?? 1)
  }
}

if (process.env.BOSSKU_SKIP_PIXEL_OFFICE_IN_NUXT_BUILD !== '1') {
  run(['run', 'build:pixel-office:bundle'])
}

run(['exec', '--', 'nuxi', 'build'])
