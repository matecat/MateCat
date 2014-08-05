#!/bin/bash

. killAnalysis.sh

#spawn new
echo "spawning daemons"
screen -d -m -S fast_staging php fastAnalysis.php
screen -d -m -S tm_staging php tmAnalysisThread.php

exit 0;
