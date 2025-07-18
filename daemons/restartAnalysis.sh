#!/bin/bash

################
#  DEPRECATED  #
################
# Only for development environment


export MATECAT_HOME="/var/www/matecat"
task_config_path=/var/www/matecat/inc/task_manager_config.ini
php_script_path=/var/www/matecat/daemons/

DIR=$( cd "$( dirname "$0")" || exit; pwd )
cd "${DIR}" || exit
/bin/bash killAnalysis.sh

# get last return code, if != 0 don't wait
rc=$?;
if [[ ${rc} -eq 0 ]]; then
    echo "Wait 10 seconds to daemons to die."
    sleep 10
fi

pid_fast=$(pgrep --full FastAnalysis.php)
pid_tm=$(pgrep --full TmAnalysis.php)

#if up, exit, ERROR
if [[ -n ${pid_fast} ]] || [[ -n ${pid_tm} ]];
then
	echo "*** FATAL ERROR: Found Already Running Processes, possible not controlled fork. Check Services. EXIT!!! ***"
	exit 1;
fi


#spawn new
echo "spawning daemons"
screen -d -m -S fast php ${php_script_path}FastAnalysis.php "${task_config_path}"
screen -d -m -S tm php ${php_script_path}TmAnalysis.php "${task_config_path}"

exit 0;
