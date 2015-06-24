#!/usr/bin/env bash

MATECAT_ROOT=`readlink -f /home/matecat/pro_matecat_com`

TEST_PATH="${MATECAT_ROOT}/lib/Utils/Analysis/monit/tmAnalysis/TestTMWorkers.php"

/usr/bin/php ${TEST_PATH}
res=$?

exit ${res}