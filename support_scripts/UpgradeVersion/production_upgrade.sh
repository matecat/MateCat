#!/usr/bin/env bash

SERVICES_DIR=/etc/init.d

MATECAT_HOME=/home/matecat/pro_matecat_com

# branch of directory
MATECAT_BRANCH="master"

USER_OWNER="www-data"

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

hist=$(git pull origin ${MATECAT_BRANCH})

ret=$?

if ! test "$ret" -eq 0
then
        echo "Git pull command failed with exit status $ret" > ${EMAILMESSAGE};
        # send an email using /bin/mail
        mail -s "$SUBJECT" "$EMAIL" < ${EMAILMESSAGE};
        exit 1
fi

chown -R ${USER_OWNER} ./*

popd
setuid ${USER_OWNER} php Upgrade.php $1


########### MONIT TOOL SERVICES UPDATE

cp "${MATECAT_HOME}/lib/Utils/Analysis/monit/fastAnalysis/fastAnalysis.sh" "/etc/init.d/fastAnalysis"
chmod +x "${SERVICES_DIR}/fastAnalysis"

cp "${MATECAT_HOME}/lib/Utils/Analysis/monit/tmAnalysis/tmAnalysis.sh" "/etc/init.d/tmAnalysis"
chmod +x "${SERVICES_DIR}/tmAnalysis"

cp "${MATECAT_HOME}/lib/Utils/Analysis/monit/tmAnalysis/testTMWorkers.sh" "/usr/local/bin/TestTMWorkers"
chmod +x "/usr/local/bin/TestTMWorkers"

# restart Daemons
monit reload