#!/usr/bin/env node
const {execSync} = require('child_process')
const fs = require('fs')
const path = require('path')

const BASELINE_PATH = path.join(__dirname, '.madge-baseline.json')

const baseline = JSON.parse(fs.readFileSync(BASELINE_PATH, 'utf8'))
const baselineCount = baseline.length

let current
try {
  const output = execSync(
    'npx madge --circular --extensions js --json public/js/',
    {encoding: 'utf8', maxBuffer: 10 * 1024 * 1024},
  )
  current = JSON.parse(output)
} catch (e) {
  if (e.stdout) {
    current = JSON.parse(e.stdout)
  } else {
    console.error('Failed to run madge:', e.message)
    process.exit(1)
  }
}

const currentCount = current.length
const diff = currentCount - baselineCount

if (diff > 0) {
  console.error(
    `\x1b[31m✖ Circular dependency regression: ${currentCount} cycles (baseline: ${baselineCount}, +${diff} new)\x1b[0m`,
  )

  const baselineSet = new Set(baseline.map((c) => JSON.stringify(c)))
  const newCycles = current.filter((c) => !baselineSet.has(JSON.stringify(c)))

  if (newCycles.length > 0) {
    console.error('\nNew cycles introduced:')
    newCycles.forEach((cycle, i) => {
      console.error(`  ${i + 1}) ${cycle.join(' → ')}`)
    })
  }

  process.exit(1)
} else if (diff < 0) {
  console.log(
    `\x1b[32m✔ Circular dependencies improved: ${currentCount} cycles (baseline: ${baselineCount}, ${diff})\x1b[0m`,
  )
  console.log(
    '\x1b[33m  ℹ Run "node check-circular-deps.js --update-baseline" to save the new baseline.\x1b[0m',
  )
} else {
  console.log(
    `\x1b[32m✔ Circular dependencies unchanged: ${currentCount} cycles\x1b[0m`,
  )
}

if (process.argv.includes('--update-baseline')) {
  fs.writeFileSync(BASELINE_PATH, JSON.stringify(current, null, 2) + '\n')
  console.log(`Baseline updated to ${currentCount} cycles.`)
}
