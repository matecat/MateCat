#!/bin/bash

. killAnalysis.sh

echo "Wait 10 seconds to daemons to die."
sleep 10

#spawn new
echo "spawning daemons"
screen -d -m -S fast php fastAnalysis.php
screen -d -m -S tm php tmAnalysisThread.php

exit 0;
