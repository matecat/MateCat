#!/bin/bash

################
#  DEPRECATED  #
################

DIR=$( cd "$( dirname "$0")" || exit; pwd )
cd "${DIR}" || exit
/bin/bash killAnalysis.sh

# get last return code, if != 0 don't wait
rc=$?;
if [[ ${rc} -eq 0 ]]; then
    echo "Wait 10 seconds to daemons to die."
    sleep 10
fi


pid_fast=$(ps faux|grep -E ".* FastAnalysis.php" |grep -v grep|grep -i SCREEN|awk '{print $2}')
pid_tm=$(ps faux|grep -E ".* TmAnalysis.php" |grep -v grep|grep -i SCREEN|awk '{print $2}')

#if up, exit, ERROR
if [[ -n ${pid_fast} ]] || [[ -n ${pid_tm} ]];
then
	echo "*** FATAL ERROR: Found Already Running Processes, possible not controlled fork. Check Services. EXIT!!! ***"
	exit 1;
fi


#spawn new
echo "spawning daemons"
screen -d -m -S fast php FastAnalysis.php ../../../inc/task_manager_config.ini
screen -d -m -S tm php TmAnalysis.php ../../../inc/task_manager_config.ini

exit 0;
