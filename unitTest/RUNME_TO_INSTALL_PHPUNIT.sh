#!/bin/bash
sudo echo "Thanks!!"

sudo pear config-set auto_discover 1
sudo pear install pear.phpunit.de/PHPUnit
sudo pear install phpunit/PHPUnit_Selenium
sudo pear install phpunit/PHPUnit_Story
sudo pear install phpunit/DbUnit

#wget https://phar.phpunit.de/phpunit.phar
#chmod +x phpunit.phar
#mv phpunit.phar /usr/local/bin/phpunit