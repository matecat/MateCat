#!/usr/bin/env bash

SERVICES_DIR=/services/

MATECAT_HOME=/home/matecat/pro_matecat_com/
MATECAT_HOME=/var/www/cattool/

# branch of directory
MATECAT_BRANCH="develop"

USER_OWNER="www-data"
USER_OWNER="domenico"

# Email Subject
SUBJECT="Git Pull failed";

# Email TO
EMAIL="domenico@translated.net";

# tmp msg file
EMAILMESSAGE="/tmp/emailmessage.txt";

##### INIT

export MATECAT_HOME;
export MATECAT_BRANCH;
pushd ${MATECAT_HOME}

branch_actual=`git symbolic-ref HEAD 2>/dev/null | cut -d"/" -f 3`

if [ "$branch_actual" != "$MATECAT_BRANCH" ];
then
        echo "Git pull command abort because pulling '$MATECAT_BRANCH' branch into '$branch_actual' " > ${EMAILMESSAGE};
        # send an email using /bin/mail
        mail -s "$SUBJECT" "$EMAIL" < ${EMAILMESSAGE};
        exit 1
fi

hist=$(setuid ${USER_OWNER} git pull origin ${MATECAT_BRANCH})

ret=$?

if ! test "$ret" -eq 0
then
        echo "Git pull command failed with exit status $ret" > ${EMAILMESSAGE};
        # send an email using /bin/mail
        mail -s "$SUBJECT" "$EMAIL" < ${EMAILMESSAGE};
        exit 1
fi

popd
setuid ${USER_OWNER} php Upgrade.php $1


########### DAEMON TOOOL SERVICES UPDATE

# Update services
# Update MultiLog
cp "${MATECAT_HOME}lib/Utils/Analysis/supervise/fastAnalysis/run" "${SERVICES_DIR}/matecat_fastAnalysis/run.sh.new"
mv -f "${SERVICES_DIR}/matecat_fastAnalysis/run.sh.new" "${SERVICES_DIR}/matecat_fastAnalysis/run.sh" # atomically replace run

cp "${MATECAT_HOME}lib/Utils/Analysis/supervise/fastAnalysis/log.sh" "${SERVICES_DIR}/matecat_fastAnalysis/log/log.sh.new"
mv -f "${SERVICES_DIR}/matecat_fastAnalysis/log/log.sh.new" "${SERVICES_DIR}/matecat_fastAnalysis/log/log.sh" # atomically replace run


# restart Daemons
svc -t "${SERVICES_DIR}/matecat_fastAnalysis"