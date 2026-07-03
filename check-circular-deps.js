#!/usr/bin/env node
const madge = require('madge')

madge('public/js/', {
  fileExtensions: ['js'],
  detectiveOptions: {
    es6: {
      skipAsyncImports: true,
    },
  },
})
  .then((res) => {
    const cycles = res.circular()

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
  })
  .catch((err) => {
    console.error('Failed to run madge:', err.message)
    process.exit(1)
  })
