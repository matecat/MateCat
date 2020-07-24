#!/bin/bash

# MateCat solution installation script developed by Tilde for ELRC3 experiment.
# Script tested on Ubuntu 18.04.3 LTS Hyper-V virtual machine on Windows 10 host machine.
# Setup tested with master branch when this commit was latest one: https://github.com/matecat/MateCat/commit/a82a3c6251525d1780ba74f32cf8c431d67dec58

# Error handling
f () {
    errcode=$? # save the exit code as the first thing done in the trap function
    echo "error $errcode"
    echo "the command executing at the time of the error was"
    echo "$BASH_COMMAND"
    echo "on line ${BASH_LINENO[0]}"
    # do some error handling, cleanup, logging, notification
    # $BASH_COMMAND contains the command that was being executed at the time of the trap
    # ${BASH_LINENO[0]} contains the line number in the script of that command
    # exit the script or return to try again, etc.
    exit $errcode  # or use some other value or do return instead
}
trap f ERR

# Set up variables for MateCat installation environment
MYSQL_ROOT_PWD="matecatRuuc" # This password will be set for mysql root user
MATECAT_USER="matecat" # This user will be created for matecat solution to work with
MATECAT_COMMIT="a82a3c6" # This commit will be checked out from MateCat git repo during setup process
MATECAT_SERVERNAME="dev.matecat.com" # MateCat's server name, must match with google's Authorized origins and Authorized redirect URIs
HOSTNAME=$(hostname) # Used as one of server alias in Apache config
MATECAT_STORAGE_DIR="/home/$MATECAT_USER/matecat-storage" # Directory where MateCat will write it's logs and temporarly files
OKAPI_COMMIT="0868188" # okapi commit that will be checked out
FILTERS_COMMIT="750dcca" # MateCat Filters commit that will be checked out
FILTERS_VERSION=1.2.5 # MateCat Filters .jar version that will be built from FILTERS_COMMIT
SMTP_HOST="smtp-host" # SMTP server
SMTP_PORT="25" # SMTP server port
SMTP_SENDER="matecat-noreply@dev.matecat.com" # Matecat system emails are sent from this address
SMTP_HOSTNAME="localhost"
GOOGLE_OAUTH_CLIENT_ID="Your client id"
GOOGLE_OAUTH_CLIENT_SECRET="Your client secret"
GOOGLE_OAUTH_CLIENT_APP_NAME="Your client app name"
GOOGLE_OAUTH_BROWSER_API_KEY="Your api key"

# Prepare apt
sudo apt-get update

# Set up MateCat dependencies

# Install apache2
sudo apt-get install -y apache2
sudo a2enmod rewrite filter deflate headers expires proxy_http.load ssl
sudo apache2ctl restart

# Install MySQL 5.7
echo "mysql-server mysql-server/root_password password $MYSQL_ROOT_PWD" | sudo debconf-set-selections
echo "mysql-server mysql-server/root_password_again password $MYSQL_ROOT_PWD" | sudo debconf-set-selections
sudo apt-get install -y mysql-server mysql-client
sudo sed -i '/sql-mode/d' /etc/mysql/mysql.conf.d/mysqld.cnf
sudo sed -i '/\[mysqld\]/a sql-mode = NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION' /etc/mysql/mysql.conf.d/mysqld.cnf
sudo systemctl restart mysql.service

# Install PHP7.0
sudo apt-add-repository ppa:ondrej/php -y
sudo apt-get install -y unzip php7.0 php7.0-mysql libapache2-mod-php7.0 php7.0-curl php7.0-json php7.0-xml php7.0-mcrypt php7.0-mbstring php7.0-zip php-xdebug
sudo sed -i 's/short_open_tag = .*/short_open_tag = On/g' /etc/php/7.0/cli/php.ini
sudo sed -i 's/memory_limit = .*/memory_limit = 1024M/g' /etc/php/7.0/cli/php.ini
sudo apache2ctl restart

# Install screen (used for FastAnalysis.php TmAnalysis.php)
sudo apt-get install -y screen

# Install Redis
sudo apt-get install -y redis-server
#sudo systemctl restart redis-server.service

# Install Git
sudo apt-get -y install git

# Install Node.js
sudo apt-get install curl
sudo curl -sL https://deb.nodesource.com/setup_10.x | sudo -E bash -
sudo apt-get install nodejs -y

# Install OpenJDK 8
sudo apt-get -y install openjdk-8-jdk

# Install Maven
sudo apt-get -y install maven

# Install ActiveMQ 5.11.3
sudo apt-get install -y openjdk-8-jdk
wget -T 10 https://archive.apache.org/dist/activemq/5.11.3/apache-activemq-5.11.3-bin.tar.gz
sudo tar xzf apache-activemq-5.11.3-bin.tar.gz -C /opt/ && rm apache-activemq-5.11.3-bin.tar.gz
sudo ln -sf /opt/apache-activemq-5.11.3/ /opt/activemq
sudo adduser -system activemq
sudo sed -i "s#activemq:/bin/false#activemq:/bin/bash#g" /etc/passwd
sudo chown -R activemq: /opt/apache-activemq-5.11.3/
sudo ln -sf /opt/activemq/bin/activemq /etc/init.d/

# Set up ActiveMQ as systemd service
sudo tee /etc/systemd/system/activemq.service << EOF
[Unit]
Description=Apache ActiveMQ
After=network.target
[Service]
Type=forking
PIDFile=/opt/activemq/data/activemq.pid
WorkingDirectory=/opt/activemq/bin
ExecStart=/etc/init.d/activemq start
ExecStop=/etc/init.d/activemq stop
Restart=on-abort
User=activemq
[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable activemq.service || echo "Continue no matter what. ExitCode [$?]"

yes | sudo /etc/init.d/activemq create /etc/default/activemq || echo "Continue no matter what. ExitCode [$?]"
sudo chown root:nogroup /etc/default/activemq
sudo chmod 600 /etc/default/activemq
sudo sed -i 's/managementContext createConnector="false"/managementContext createConnector="true"/g' /opt/activemq/conf/activemq.xml
sudo ln -sf /etc/init.d/activemq /usr/bin/activemq
sudo systemctl stop activemq.service
sudo systemctl start activemq.service

# Prep user acc
id -u "$MATECAT_USER" || sudo adduser --disabled-password --gecos "" $MATECAT_USER
sudo usermod -a -G www-data $MATECAT_USER
# Matecat storage configuration
sudo mkdir -p $MATECAT_STORAGE_DIR
sudo chmod g+w -R $MATECAT_STORAGE_DIR
sudo chown -R www-data:www-data $MATECAT_STORAGE_DIR
# Check out MateCat source code
sudo rm -rf /home/$MATECAT_USER/cattool
sudo -i -u $MATECAT_USER git clone https://github.com/matecat/MateCat.git cattool
sudo -u $MATECAT_USER -H sh -c "cd /home/$MATECAT_USER/cattool; git fetch --all"
sudo -u $MATECAT_USER -H sh -c "cd /home/$MATECAT_USER/cattool; git checkout $MATECAT_COMMIT"
sudo -u $MATECAT_USER -H sh -c "cp /home/$MATECAT_USER/cattool/inc/task_manager_config.ini.sample /home/$MATECAT_USER/cattool/inc/task_manager_config.ini"
# Set up MateCat config.ini
sudo -u $MATECAT_USER -H sh -c "cp /home/$MATECAT_USER/cattool/inc/config.ini.sample /home/$MATECAT_USER/cattool/inc/config.ini"
sudo sed -i "s|STORAGE_DIR = \"/home/matecat/cattool/storage\"|STORAGE_DIR = \"$MATECAT_STORAGE_DIR\"|g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|CLI_HTTP_HOST = \"http://localhost\"|CLI_HTTP_HOST = \"https://$MATECAT_SERVERNAME\"|g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|COOKIE_DOMAIN = \"localhost\"|COOKIE_DOMAIN = \"$MATECAT_SERVERNAME\"|g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|SSE_BASE_URL      = \"localhost/sse\"|SSE_BASE_URL = \"$MATECAT_SERVERNAME/sse\"|g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|FILE_STORAGE_METHOD = 's3'|FILE_STORAGE_METHOD = 'fs'|g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|^SMTP_HOST = .*|SMTP_HOST = \'${SMTP_HOST}\'|" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|^SMTP_PORT = .*|SMTP_PORT = \'${SMTP_PORT}\'|" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|^SMTP_HOSTNAME = .*|SMTP_HOSTNAME = \'${SMTP_HOSTNAME}\'|" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|^SMTP_SENDER = .*|SMTP_SENDER = \'${SMTP_SENDER}\'|" /home/$MATECAT_USER/cattool/inc/config.ini

# Change default e-mail addresses in INIT.php
sudo sed -i "s|cattool@matecat.com|${SMTP_SENDER}|" /home/$MATECAT_USER/cattool/inc/INIT.php
sudo sed -i "s|no-reply@matecat.com|${SMTP_SENDER}|" /home/$MATECAT_USER/cattool/inc/INIT.php

# Set up login_secret.dat file
sudo touch /home/$MATECAT_USER/cattool/inc/login_secret.dat
sudo chown www-data:matecat /home/$MATECAT_USER/cattool/inc/login_secret.dat
sudo chmod 550 /home/$MATECAT_USER/cattool/inc/login_secret.dat

# Download composer installer
sudo -u $MATECAT_USER -H sh -c "cd /home/$MATECAT_USER/cattool; php -r \"copy('https://getcomposer.org/installer', 'composer-setup.php');\""
# Install composer
sudo -u $MATECAT_USER -H sh -c "cd /home/$MATECAT_USER/cattool;php /home/$MATECAT_USER/cattool/composer-setup.php"
# Remove composer installer
sudo -u $MATECAT_USER -H sh -c "cd /home/$MATECAT_USER/cattool;php -r \"unlink('composer-setup.php');\""
# Install MateCat prereqs
sudo -u $MATECAT_USER -H sh -c "cd /home/$MATECAT_USER/cattool;php composer.phar install"

# Set up front-end code

cd /home/$MATECAT_USER/cattool/nodejs/
sudo npm install
cp config.ini.sample config.ini
sed -i 's|^host =.*|host = localhost|' config.ini
cd /home/$MATECAT_USER/

sudo tee /etc/systemd/system/matecat-nodejs.service << EOF
[Unit]
Description=Node.js for realtime chat within matecat
After=network.target

[Service]
ExecStart=/usr/bin/node /home/$MATECAT_USER/cattool/nodejs/server.js
Restart=always
User=www-data
WorkingDirectory=/home/$MATECAT_USER/cattool/nodejs

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable matecat-nodejs.service
sudo systemctl start matecat-nodejs.service

cd /home/$MATECAT_USER/cattool/support_scripts/grunt
sudo npm config set user 0
sudo npm config set unsafe-perm true
sudo npm install --save-dev @babel/core
sudo npm install --save-dev webpack@2
sudo rm -rf node_modules
sudo npm install
sudo npm install grunt
sudo npm install -g grunt-cli
sudo grunt deploy

cd /home/$MATECAT_USER/

# Apply MateCat sql
#Crate DB if not exist
if [ ! -d /var/lib/mysql/matecat ]; then
  echo "No matecat DB exists, creating DB"
  mysql -u root -p$MYSQL_ROOT_PWD < /home/$MATECAT_USER/cattool/INSTALL/matecat.sql
else
  echo "Matecat DB exists, skipping"
fi

# Turn on the analysis daemon
sudo crontab -l > mycron || echo "^ Safe to ignore \"no crontab for root\""
# Echo new cron into cron file
echo "@reboot /bin/bash /home/$MATECAT_USER/cattool/daemons/restartAnalysis.sh" >> mycron
# Install new cron file
sudo -u www-data crontab mycron
sudo rm mycron

sudo touch /home/$MATECAT_USER/cattool/daemons/.num_processes
sudo chown www-data:matecat /home/$MATECAT_USER/cattool/daemons/.num_processes
sudo -u www-data -H sh -c "echo '25' > /home/$MATECAT_USER/cattool/daemons/.num_processes"
sudo -u www-data /bin/bash /home/$MATECAT_USER/cattool/daemons/restartAnalysis.sh
# wait for restartAnalysis.sh
sleep 5
# Permissions, improvments needed
sudo chown -R www-data $MATECAT_STORAGE_DIR
sudo chmod -R 775 $MATECAT_STORAGE_DIR

# Set up Google auth
sudo -u $MATECAT_USER -H sh -c "cp /home/$MATECAT_USER/cattool/inc/oauth_config.ini.sample /home/$MATECAT_USER/cattool/inc/oauth_config.ini"
sudo sed -i "s|OAUTH_CLIENT_ID       = |OAUTH_CLIENT_ID       = \"$GOOGLE_OAUTH_CLIENT_ID\"|g" /home/$MATECAT_USER/cattool/inc/oauth_config.ini
sudo sed -i "s|OAUTH_CLIENT_SECRET   = |OAUTH_CLIENT_ID       = \"$GOOGLE_OAUTH_CLIENT_SECRET\"|g" /home/$MATECAT_USER/cattool/inc/oauth_config.ini
sudo sed -i "s|OAUTH_CLIENT_APP_NAME = Matecat|OAUTH_CLIENT_APP_NAME       = \"$GOOGLE_OAUTH_CLIENT_APP_NAME\"|g" /home/$MATECAT_USER/cattool/inc/oauth_config.ini
sudo sed -i "s|OAUTH_BROWSER_API_KEY = |OAUTH_BROWSER_API_KEY       = \"$GOOGLE_OAUTH_BROWSER_API_KEY\"|g" /home/$MATECAT_USER/cattool/inc/oauth_config.ini
# Create empty file, because matecat tries to open in to save encryption key
sudo -u $MATECAT_USER -H sh -c "touch /home/$MATECAT_USER/cattool/inc/oauth-token-key.txt"
# Repeat chown on [storage] directory - matecat can't write logs (log.txt)
sudo chown www-data:www-data /home/$MATECAT_USER/cattool/inc/oauth-token-key.txt

# Install filters service
# Delete contents. Git cant clone in to non-empty directory on repeated installs
sudo rm -rf ~/okapi
git clone https://bitbucket.org/okapiframework/okapi.git
cd okapi/
git checkout $OKAPI_COMMIT
mvn clean install -DskipTests

git clone https://github.com/matecat/MateCat-Filters.git
cd MateCat-Filters/filters
git checkout $FILTERS_COMMIT
mvn clean package -DskipTests

# Copy files to destination folder for filter service
sudo mkdir -p /opt/filters
sudo cp target/filters-$FILTERS_VERSION.jar /opt/filters/
sudo cp src/main/resources/config.sample.properties /opt/filters/config.properties

# MateCat filter as systemd service
sudo tee /etc/systemd/system/matecat-filter.service << EOF
[Unit]
Description=Matecat filter service

[Service]
WorkingDirectory=/opt/filters/
ExecStart=/usr/bin/java -cp ".:filters-${FILTERS_VERSION}.jar" com.matecat.converter.Main
Restart=always
RestartSec=10
SyslogIdentifier=Matecat-Filter
User=${MATECAT_USER}
[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable matecat-filter.service
sudo systemctl start matecat-filter.service

sudo sed -i "s/FILTERS_ADDRESS.*/FILTERS_ADDRESS = http:\/\/localhost:8732/g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s/FILTERS_MASHAPE_KEY.*/FILTERS_MASHAPE_KEY = /g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo echo "127.0.0.1    $MATECAT_SERVERNAME" >> /etc/hosts

# Set up self signed SSL certificate
sudo mkdir -p /etc/apache2/ssl-cert
sudo touch ~/.rnd
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/apache2/ssl-cert/$MATECAT_SERVERNAME.key -out /etc/apache2/ssl-cert/$MATECAT_SERVERNAME.crt -subj "/C=EU/ST=./L=./O=Matecat/OU=Matecat/CN=${MATECAT_SERVERNAME}"
sudo rm ~/.rnd
# Set up Apache2 MateCat vhost
sudo touch /etc/apache2/sites-available/matecat-ssl.conf
sudo cat> /etc/apache2/sites-available/matecat-ssl.conf << EOL
# Redirect to SSL
<VirtualHost *:80>
    ServerName ${MATECAT_SERVERNAME}
    ServerAlias www.${MATECAT_SERVERNAME}
    ServerAlias ${HOSTNAME}

  Redirect permanent / https://${MATECAT_SERVERNAME}/
</VirtualHost>

## SSL site config
<VirtualHost *:443>

    ServerAdmin webmaster@localhost
    ServerName ${MATECAT_SERVERNAME}
    ServerAlias www.${MATECAT_SERVERNAME}
    ServerAlias ${HOSTNAME}
    DocumentRoot /home/${MATECAT_USER}/cattool
    DirectoryIndex index.php index.php3 index.html index.htm index.shtml
    <Directory />
        Options FollowSymLinks
        AllowOverride None
    </Directory>

    SSLEngine		On
    SSLProtocol         -all +TLSv1.2
    SSLCipherSuite      ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384
    SSLProxyEngine	On

    SSLCertificateFile /etc/apache2/ssl-cert/${MATECAT_SERVERNAME}.crt
    SSLCertificateKeyFile /etc/apache2/ssl-cert/${MATECAT_SERVERNAME}.key


    <Directory /home/${MATECAT_USER}/cattool/ >

            Options All
            AllowOverride All
            Require all granted

            ExpiresActive On
            ExpiresByType text/html "access plus 1 minute"
            ExpiresByType text/css "access plus 1 minute"
            ExpiresByType text/javascript "access plus 1 minute"
            ExpiresByType image/gif "access plus 1 week"
            ExpiresByType image/jpeg "access plus 1 week"
            ExpiresByType image/jpg "access plus 1 week"
            ExpiresByType image/png "access plus 1 week"
            ExpiresByType image/vnd.microsoft.icon "access plus 1 week"
            ExpiresByType image/ico "access plus 1 week"
            ExpiresByType application/x-shockwave-flash "access plus 1 week"

    </Directory>

    php_flag register_globals off
    php_flag magic_quotes_gpc off
    ErrorLog /var/log/apache2/matecat.error.log
    CustomLog /var/log/apache2/matecat.access.log combined
    ServerSignature Off

    <Location /sse/ >
        ProxyPass http://localhost:7788/
        ProxyPassReverse http://localhost:7788/
    </Location>

</VirtualHost>
EOL

sudo a2dissite 000-default.conf
sudo a2ensite matecat-ssl.conf
echo "Restarting apache2 - activating the new configuration"
sudo systemctl reload apache2

echo "All done"

