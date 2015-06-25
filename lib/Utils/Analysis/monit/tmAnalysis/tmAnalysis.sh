#!/bin/sh

# Installation
# - Move this to /etc/init.d/myservice
# - chmod +x this
#
# Starting and stopping
# - Start: `service myservice start` or `/etc/init.d/myservice start`
# - Stop: `service myservice stop` or `/etc/init.d/myservice stop`

#ref http://till.klampaeckel.de/blog/archives/94-start-stop-daemon,-Gearman-and-a-little-PHP.html
#ref http://unix.stackexchange.com/questions/85033/use-start-stop-daemon-for-a-php-server/85570#85570
#ref http://serverfault.com/questions/229759/launching-a-php-daemon-from-an-lsb-init-script-w-start-stop-daemon

NAME=tmAnalysis
MATECAT_ROOT=`readlink -f /home/matecat/pro_matecat_com`

DESC="Daemon for TM Analysis PHP CLI script"
PIDFILE="/var/run/${NAME}.pid"
LOGFILE="/var/log/${NAME}.log"

DAEMON="/usr/bin/php"
DAEMON_PATH="${MATECAT_ROOT}/lib/Utils/Analysis"
DAEMON_OPTS="${DAEMON_PATH}/tmAnalysisThread.php"

test -x ${DAEMON} || exit 0

set -e

case "$1" in
    start)
        echo -n "Starting ${DESC}: "
        cd ${DAEMON_PATH}
        exec ${DAEMON} ${DAEMON_OPTS} 1>&2 2>/dev/null & echo $! > ${PIDFILE}
        echo "${DAEMON} ${DAEMON_OPTS} 1>&2 2>/dev/null & echo $! > ${PIDFILE}"
        echo "$NAME."
        ;;
    stop)
        echo -n "Stopping $DESC: "
        pid_tm=`ps faux|grep tmAnalysisThread.php|grep -v grep|grep -v SCREEN|awk '{print $2}'`
        kill ${pid_tm}

        echo "$NAME."
        rm -f ${PIDFILE}
        ;;
    restart|force-reload)
        echo -n "Restarting $DESC: "
        pid_tm=`ps faux|grep tmAnalysisThread.php|grep -v grep|grep -v SCREEN|awk '{print $2}'`
        kill ${pid_tm}
        echo "Wait 10 seconds"
        sleep 10
        cd ${DAEMON_PATH}
        exec ${DAEMON} ${DAEMON_OPTS} 1>&2 2>/dev/null & echo $! > ${PIDFILE}
        echo "${DAEMON} ${DAEMON_OPTS} 1>&2 2>/dev/null & echo $! > ${PIDFILE}"
        echo "$NAME."
        ;;
    *)
        N=/etc/init.d/${NAME}
        echo "Usage: $N {start|stop|restart|force-reload}" >&2
        exit 1
        ;;
esac

exit 0