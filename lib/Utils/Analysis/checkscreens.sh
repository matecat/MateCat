#!/bin/bash

################
#  IN USAGE    #
################

#get PIDs
pid_fast=`ps faux|grep FastAnalysis.php|grep -v grep|grep -i SCREEN|awk '{print $2}'`
pid_tm=`ps faux|grep TmAnalysis.php|grep -v grep|grep -i SCREEN|awk '{print $2}'`

path=/var/www/matecat/inc/task_manager_config.ini
path_php=/var/www/matecat/daemons/


if [[ -n ${pid_fast} ]];
then
        echo "Fast Analysis is Running..."
else
        echo "Spawning Fast Analysis..." `date +%d/%m/%Y\ %H:%M:%S` >> /var/www/matecat/daemons/shutdown.log 2>&1;
        screen -d -m -S fast php ${path_php}FastAnalysis.php ${path}
fi

if [[ -n ${pid_tm} ]];
then
        echo "TM Analysis is Running..."
else
        echo "Spawning TM analysis" `date +%d/%m/%Y\ %H:%M:%S` >> /var/www/matecat/daemons/shutdown.log 2>&1;
        screen -d -m -S tm php ${path_php}TmAnalysis.php ${path}
fi
