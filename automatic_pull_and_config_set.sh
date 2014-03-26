#!/bin/bash

# root directory of project
ROOT="/var/www/cattool"

# branch of directory
branch_default="develop"

# Email Subject
SUBJECT="Git Pull failed";

# Email TO
EMAIL="domenico@translated.net";

# tmp msg file
EMAILMESSAGE="/tmp/emailmessage.txt";

##### INIT


cd $ROOT

branch_actual=`git symbolic-ref HEAD 2>/dev/null | cut -d"/" -f 3`

if [ "$branch_actual" != "$branch_default" ]; 
then
        echo "Git pull command abort because pulling '$branch_default' branch into '$branch_actual' " > $EMAILMESSAGE;
        # send an email using /bin/mail
        mail -s "$SUBJECT" "$EMAIL" < $EMAILMESSAGE;
        exit 1
fi

hist=$(git pull origin $branch_default)

ret=$?

if ! test "$ret" -eq 0
then
        echo "Git pull command failed with exit status $ret" > $EMAILMESSAGE;
        # send an email using /bin/mail
        mail -s "$SUBJECT" "$EMAIL" < $EMAILMESSAGE;
        exit 1
fi


cp -f inc/config.inc.sample.php inc/config.inc.php
sed -i 's/self\:\:\$DB_SERVER   \= \"localhost\"\;/self::$DB_SERVER   = "10.30.1.225";/g' inc/config.inc.php;
sed -i 's/self\:\:\$DB_DATABASE \= \"matecat\";/self::$DB_DATABASE = "matecat_sandbox";/g' inc/config.inc.php;
sed -i 's/self::$MEMCACHE_SERVERS = array( \/* \'localhost:11211\' => 1 *\/ );/self::$MEMCACHE_SERVERS = array(  \'localhost:11211\' => 1 );/g' inc/config.inc.php;

