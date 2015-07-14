#!/bin/bash
sudo echo "Installing ActiveMq\n"

J=`java --version`
if [ -z "$J"]; then
	sudo apt-get install openjdk-8-jre
fi 

cd /tmp/

wget http://mirrors.gigenet.com/apache/activemq/5.11.1/apache-activemq-5.11.1-bin.tar.gz

tar xzf apache-activemq-5.11.1-bin.tar.gz && rm apache-activemq-5.11.1-bin.tar.gz

sudo mv apache-activemq-5.11.1 /opt

sudo ln -sf /opt/apache-activemq-5.11.1/ /opt/activemq

sudo adduser -system activemq

sudo sed -i "s#activemq:/bin/false#activemq:/bin/bash#g" /etc/passwd

sudo chown -R activemq: /opt/apache-activemq-5.11.1/

sudo ln -sf /opt/activemq/bin/activemq /etc/init.d/

sudo printf "\n@reboot /usr/bin/activemq\n" >> /var/spool/cron/crontabs/root

sudo /etc/init.d/activemq create /etc/default/activemq

sudo chown root:nogroup /etc/default/activemq

sudo chmod 600 /etc/default/activemq

sudo sed -i 's/managementContext createConnector="false"/managementContext createConnector="true"/g' /opt/activemq/conf/activemq.xml

sudo ln -s /etc/init.d/activemq /usr/bin/activemq

sudo activemq start

