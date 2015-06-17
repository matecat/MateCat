#!/bin/bash
DIR=$( cd "$( dirname "$0")"; pwd )
cd ${DIR}
/bin/bash killAnalysis.sh

# get last return code, if != 0 don't wait
rc=$?;
if [[ ${rc} -eq 0 ]]; then
    echo "Wait 10 seconds to daemons to die."
    sleep 10
fi

#spawn new
echo "spawning daemons"
screen -d -m -S fast php fastAnalysis.php
screen -d -m -S tm php tmAnalysisThread.php

exit 0;
