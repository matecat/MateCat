#!/bin/bash
# Runs the Jest suite and the nodejs (socket server) suite inside the test-node container.
# Called by the "JS tests" job of .github/workflows/_ci-cd.yml, which mounts /ci-out (reports)
# and /ci-cache (yarn and npm caches).
#
# jest.config.js keeps maxWorkers at 50% and recycles a worker above 256MB, which suits a
# developer machine. The CI runner has nothing else to do, so this run uses every core and lets
# a worker grow to 1GB before Jest restarts it.
set -euo pipefail
cd /var/www/matecat

npm install -g yarn
yarn install > /dev/null
yarn coverage \
    --maxWorkers=100% \
    --workerIdleMemoryLimit=1GB \
    --coverageReporters="text-summary" \
    --coverageReporters="clover" \
    --coverageDirectory=/ci-out/js-coverage \
    --forceExit
mv /ci-out/js-coverage/clover.xml /ci-out/js-coverage.xml

cd nodejs
cp config.ini.sample config.ini
npm install
npm run coverage
cp .coverage/clover.xml /ci-out/node-coverage.xml
