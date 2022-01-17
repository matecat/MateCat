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
MATECAT_STORAGE_DIR="/home/$MATECAT_USER/cattool/storage" # Directory where MateCat will write it's logs and temporarly files
OKAPI_COMMIT="0868188" # okapi commit that will be checked out
FILTERS_COMMIT="750dcca" # MateCat Filters commit that will be checked out
FILTERS_VERSION=1.2.5 # MateCat Filters .jar version that will be built from FILTERS_COMMIT

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
sudo apt-get install -y php7.0 php7.0-mysql libapache2-mod-php7.0 php7.0-curl php7.0-json php7.0-xml php7.0-mcrypt php7.0-mbstring php7.0-zip php-xdebug
sudo sed -i 's/short_open_tag = .*/short_open_tag = On/g' /etc/php/7.0/cli/php.ini
sudo sed -i 's/memory_limit = .*/memory_limit = 1024M/g' /etc/php/7.0/cli/php.ini
sudo apache2ctl restart

# Install screen
sudo apt-get install -y screen

# Install Redis
sudo apt-get install -y redis-server
sudo systemctl restart redis-server.service

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
wget https://archive.apache.org/dist/activemq/5.11.3/apache-activemq-5.11.3-bin.tar.gz
sudo tar xzf apache-activemq-5.11.3-bin.tar.gz -C /opt/ && rm apache-activemq-5.11.3-bin.tar.gz
sudo ln -sf /opt/apache-activemq-5.11.3/ /opt/activemq
sudo adduser -system activemq
sudo sed -i "s#activemq:/bin/false#activemq:/bin/bash#g" /etc/passwd
sudo chown -R activemq: /opt/apache-activemq-5.11.3/
sudo ln -sf /opt/activemq/bin/activemq /etc/init.d/

# Set up ActiveMQ as systemd service
sudo bash <<EOF
echo "[Unit]" > /etc/systemd/system/activemq.service
echo "Description=Apache ActiveMQ" >> /etc/systemd/system/activemq.service
echo "After=network-online.target" >> /etc/systemd/system/activemq.service
echo "[Service]" >> /etc/systemd/system/activemq.service
echo "Type=forking" >> /etc/systemd/system/activemq.service
echo "PIDFile=/opt/activemq/data/activemq.pid" >> /etc/systemd/system/activemq.service
echo "WorkingDirectory=/opt/activemq/bin" >> /etc/systemd/system/activemq.service
echo "ExecStart=/etc/init.d/activemq start" >> /etc/systemd/system/activemq.service
echo "ExecStop=/etc/init.d/activemq stop" >> /etc/systemd/system/activemq.service
echo "Restart=on-abort" >> /etc/systemd/system/activemq.service
echo "User=activemq" >> /etc/systemd/system/activemq.service
echo "[Install]" >> /etc/systemd/system/activemq.service
echo "WantedBy=multi-user.target" >> /etc/systemd/system/activemq.service
EOF
sudo systemctl daemon-reload
sudo systemctl enable activemq.service || echo "Continue no matter what. ExitCode [$?]"

yes | sudo /etc/init.d/activemq create /etc/default/activemq || echo "Continue no matter what. ExitCode [$?]"
sudo chown root:nogroup /etc/default/activemq
sudo chmod 600 /etc/default/activemq
sudo sed -i 's/managementContext createConnector="false"/managementContext createConnector="true"/g' /opt/activemq/conf/activemq.xml
sudo ln -sf /etc/init.d/activemq /usr/bin/activemq
sudo systemctl start activemq.service

# Check out MateCat source code
id -u "$MATECAT_USER" || sudo adduser --disabled-password --gecos "" $MATECAT_USER
sudo rm -rf /home/$MATECAT_USER/cattool
sudo -i -u $MATECAT_USER git clone https://github.com/matecat/MateCat.git cattool
sudo -u $MATECAT_USER -H sh -c "cd /home/$MATECAT_USER/cattool; git fetch --all"
sudo -u $MATECAT_USER -H sh -c "cd /home/$MATECAT_USER/cattool; git checkout $MATECAT_COMMIT"
sudo -u $MATECAT_USER -H sh -c "cp /home/$MATECAT_USER/cattool/inc/task_manager_config.ini.sample /home/$MATECAT_USER/cattool/inc/task_manager_config.ini"
# Set up MateCat config.ini
sudo -u $MATECAT_USER -H sh -c "cp /home/$MATECAT_USER/cattool/inc/config.ini.sample /home/$MATECAT_USER/cattool/inc/config.ini"
sudo sed -i "s|STORAGE_DIR = \"/home/matecat/cattool/storage\"|STORAGE_DIR = \"$MATECAT_STORAGE_DIR\"|g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|CLI_HTTP_HOST = \"http://localhost\"|CLI_HTTP_HOST = \"http://$MATECAT_SERVERNAME\"|g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|COOKIE_DOMAIN = \"localhost\"|COOKIE_DOMAIN = \"$MATECAT_SERVERNAME\"|g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|SSE_BASE_URL      = \"localhost/sse\"|SSE_BASE_URL = \"$MATECAT_SERVERNAME/sse\"|g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s|FILE_STORAGE_METHOD = 's3'|FILE_STORAGE_METHOD = 'fs'|g" /home/$MATECAT_USER/cattool/inc/config.ini

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
echo "screen -d -m -S 'node' node \/home\/$MATECAT_USER\/cattool\/nodejs\/server.js" >> /etc/rc.local
cd /home/$MATECAT_USER/cattool/support_scripts/grunt
sudo npm install grunt grunt-cli -g
sudo npm install
sudo grunt deploy
cd /home/$MATECAT_USER/

# Apply MateCat sql
mysql -u root -p$MYSQL_ROOT_PWD < /home/$MATECAT_USER/cattool/INSTALL/matecat.sql

# Set up Apache2 MateCat vhost
sudo cp /home/$MATECAT_USER/cattool/INSTALL/matecat-vhost.conf.sample /etc/apache2/sites-available/matecat-vhost.conf
sudo sed -i "s/@@@path@@@/\/home\/$MATECAT_USER\/cattool/g" /etc/apache2/sites-available/matecat-vhost.conf
sudo sed -i -- "s/localhost/$MATECAT_SERVERNAME/g" /etc/apache2/sites-available/matecat-vhost.conf
sudo sed -i -- "s/#ServerName www.example.com/ServerName DONT-USE-ME-DOT-COM/g" /etc/apache2/sites-enabled/000-default.conf
sudo a2ensite matecat-vhost.conf
sudo apache2ctl restart

# Turn on the analysis daemon
sudo -u $MATECAT_USER -H sh -c "echo '25' > /home/$MATECAT_USER/cattool/daemons/.num_processes"
sudo -u $MATECAT_USER /bin/bash /home/$MATECAT_USER/cattool/daemons/restartAnalysis.sh
sudo crontab -l > mycron || echo "^ Safe to ignore \"no crontab for root\""
# Remove restartAnalysis cron task(s) on repeated installs
sed -i '/restartAnalysis/d' mycron
# Echo new cron into cron file
echo "@reboot /bin/bash /home/$MATECAT_USER/cattool/daemons/restartAnalysis.sh" >> mycron
# Install new cron file
sudo -u $MATECAT_USER crontab mycron
sudo rm mycron

# Allow writing to logs directory
sudo chown -R www-data $MATECAT_STORAGE_DIR

# Set up Google auth
sudo -u $MATECAT_USER -H sh -c "cp /home/$MATECAT_USER/cattool/inc/oauth_config.ini.sample /home/$MATECAT_USER/cattool/inc/oauth_config.ini"
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
sudo bash <<EOF
echo "[Unit]" > /etc/systemd/system/matecat-filter.service
echo "Description=Matecat filter service" >> /etc/systemd/system/matecat-filter.service
echo "[Service]" >> /etc/systemd/system/matecat-filter.service
echo "WorkingDirectory=/opt/filters/" >> /etc/systemd/system/matecat-filter.service
echo "ExecStart=/usr/bin/java -cp \".:filters-${FILTERS_VERSION}.jar\" com.matecat.converter.Main" >> /etc/systemd/system/matecat-filter.service
echo "Restart=always" >> /etc/systemd/system/matecat-filter.service
echo "RestartSec=10" >> /etc/systemd/system/matecat-filter.service
echo "SyslogIdentifier=Matecat-Filter" >> /etc/systemd/system/matecat-filter.service
echo "[Install]" >> /etc/systemd/system/matecat-filter.service
echo "WantedBy=multi-user.target" >> /etc/systemd/system/matecat-filter.service
EOF

sudo systemctl daemon-reload
sudo systemctl enable matecat-filter.service
sudo systemctl start matecat-filter.service

sudo sed -i "s/FILTERS_ADDRESS.*/FILTERS_ADDRESS = http:\/\/localhost:8732/g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo sed -i "s/FILTERS_RAPIDAPI_KEY.*/FILTERS_RAPIDAPI_KEY = /g" /home/$MATECAT_USER/cattool/inc/config.ini
sudo chown -R www-data:$MATECAT_USER $MATECAT_STORAGE_DIR
sudo echo "127.0.0.1    $MATECAT_SERVERNAME" >> /etc/hosts

# Set up SSL
sudo mkdir /etc/apache2/ssl-cert
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/apache2/ssl-cert/$MATECAT_SERVERNAME.key -out /etc/apache2/ssl-cert/$MATECAT_SERVERNAME.crt -subj "/C=US/ST=./L=./O=Matecat/OU=Matecat/CN=dev.matecat.com"

sudo touch /etc/apache2/sites-available/matecat-ssl.conf
sudo cat>/etc/apache2/sites-available/matecat-ssl.conf << EOL
<VirtualHost *:443>

    ServerAdmin webmaster@localhost
    ServerName ${MATECAT_SERVERNAME}

    DocumentRoot /home/matecat/cattool
    DirectoryIndex index.php index.php3 index.html index.htm index.shtml
    <Directory />
        Options FollowSymLinks
        AllowOverride None
    </Directory>

    SSLEngine On
    SSLProtocol all -SSLv3 -SSLv2

    SSLProxyEngine On

    SSLCertificateFile /etc/apache2/ssl-cert/${MATECAT_SERVERNAME}.crt
    SSLCertificateKeyFile /etc/apache2/ssl-cert/${MATECAT_SERVERNAME}.key


    <Directory /home/matecat/cattool/ >

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

<VirtualHost *:443>

    ServerAdmin webmaster@localhost
    ServerName 0.ajax.${MATECAT_SERVERNAME}
    ServerAlias 1..ajax.${MATECAT_SERVERNAME}
    ServerAlias 2.ajax.${MATECAT_SERVERNAME}
    ServerAlias 3.ajax.${MATECAT_SERVERNAME}

    DocumentRoot /home/matecat/cattool
    DirectoryIndex index.php index.php3 index.html index.htm index.shtml
    <Directory />
        Options FollowSymLinks
        AllowOverride None
    </Directory>

    SSLEngine On
    SSLProtocol all -SSLv3 -SSLv2

    SSLProxyEngine On

    SSLCertificateFile /etc/apache2/ssl-cert/${MATECAT_SERVERNAME}.crt
    SSLCertificateKeyFile /etc/apache2/ssl-cert/${MATECAT_SERVERNAME}.key

    <Directory /home/matecat/cattool/>

            Options All
            AllowOverride All
            Order allow,deny
            allow from all

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

sudo a2ensite matecat-ssl.conf
sudo service apache2 restart

