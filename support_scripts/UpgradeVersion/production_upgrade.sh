#!/usr/bin/env bash

MATECAT_HOME=/home/matecat/pro_matecat_com/

# branch of directory
MATECAT_BRANCH="master"

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

hist=$(setuid www-data git pull origin ${MATECAT_BRANCH})

ret=$?

if ! test "$ret" -eq 0
then
        echo "Git pull command failed with exit status $ret" > ${EMAILMESSAGE};
        # send an email using /bin/mail
        mail -s "$SUBJECT" "$EMAIL" < ${EMAILMESSAGE};
        exit 1
fi

popd
setuid www-data php Upgrade.php $1