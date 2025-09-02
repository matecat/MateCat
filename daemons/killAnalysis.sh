#!/bin/bash

################
#  DEPRECATED  #
################

#get PIDs
pid_fast=$(pgrep --full FastAnalysis.php)
pid_tm=$(pgrep --full TmAnalysis.php)

#kill procs, if up
if [[ -n ${pid_fast} ]];
then
	echo "killing fast"
	pkill --full FastAnalysis.php
else
	echo "fast was not running"
        pid_fast="0"
fi

if [[ -n ${pid_tm} ]];
then
	echo "killing tm"
	pkill --full TmAnalysis.php
else
	echo "tm was not running"
        pid_tm="0"
fi

if [ "${pid_tm}" = "0" ] && [ "${pid_fast}" = "0" ]; then
    echo "No demons found to kill!"
    exit 1;
fi

exit 0;