#!/bin/bash

. killAnalysis.sh

#spawn new
echo "spawning daemons"
screen -d -m -S fast php fastAnalysis.php
screen -d -m -S tm php tmAnalysisThread.php

exit 0;
