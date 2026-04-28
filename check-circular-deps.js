#!/usr/bin/env node
const {execSync} = require('child_process')

let cycles
try {
  const output = execSync(
    'npx madge --circular --extensions js --json public/js/',
    {encoding: 'utf8', maxBuffer: 10 * 1024 * 1024},
  )
  cycles = JSON.parse(output)
} catch (e) {
  if (e.stdout) {
    cycles = JSON.parse(e.stdout)
  } else {
    console.error('Failed to run madge:', e.message)
    process.exit(1)
  }
}

if (cycles.length > 0) {
  console.error(
    `\x1b[31m✖ Found ${cycles.length} circular dependencies:\x1b[0m\n`,
  )
  cycles.forEach((cycle, i) => {
    console.error(`  ${i + 1}) ${cycle.join(' → ')}`)
  })
  process.exit(1)
} else {
  console.log('\x1b[32m✔ No circular dependencies\x1b[0m')
}
