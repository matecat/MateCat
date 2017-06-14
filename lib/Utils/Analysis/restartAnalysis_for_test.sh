#!/bin/bash
set -e


DIR=$( cd "$( dirname "$0")"; pwd )
cd ${DIR}
/bin/bash killAnalysis.sh

# get last return code, if != 0 don't wait
rc=$?;
if [[ ${rc} -eq 0 ]]; then
    echo "Wait 10 seconds to daemons to die."
    sleep 10
fi

echo "Set test Environment"
cp ../../../inc/config.test.ini ../../../inc/config.ini

pid_fast=`ps faux|grep FastAnalysis.php ../../../inc/task_manager_config.ini|grep -v grep|grep -v SCREEN|awk '{print $2}'`
pid_tm=`ps faux|grep TmAnalysis.php ../../../inc/task_manager_config.ini|grep -v grep|grep -v SCREEN|awk '{print $2}'`

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

echo "Reset Environment"
sleep 1
cp ../../../inc/config.development.ini ../../../inc/config.ini

exit 0;
