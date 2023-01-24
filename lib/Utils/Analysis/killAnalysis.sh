#!/bin/bash

################
#  DEPRECATED  #
################

#get PIDs
pid_fast=$(ps faux|grep -E ".* FastAnalysis.php" |grep -v grep|grep -i SCREEN|awk '{print $2}')
pid_tm=$(ps faux|grep -E ".* TmAnalysis.php" |grep -v grep|grep -i SCREEN|awk '{print $2}')

#kill procs, if up
if [[ -n ${pid_fast} ]];
then
	echo "killing fast"
	kill "${pid_fast}";
else
	echo "fast was not running"
        pid_fast=0
fi

if [[ -n ${pid_tm} ]];
then
	echo "killing tm"
	kill "${pid_tm}";
else
	echo "tm was not running"
        pid_tm=0
fi

if [ ${pid_tm} -eq 0 ] && [ ${pid_fast} -eq 0 ]; then
    echo "No demons found to kill!"
    exit 1;
fi

exit 0;
