#!/bin/bash

cd /vagrant

set -e

function removeDownloads {
  rm -f puppetlabs-release-wheezy.deb
}

if ! ( dpkg -l puppet > /dev/null 2>&1 ); then
  removeDownloads

  wget https://apt.puppetlabs.com/puppetlabs-release-wheezy.deb
  sudo dpkg -i puppetlabs-release-wheezy.deb
  sudo apt-get update
  sudo apt-get install -y puppet
fi
removeDownloads

sudo puppet module install ripienaar-concat
sudo puppet module install puppetlabs-stdlib

sudo puppet apply --modulepath=support_scripts/puppet/modules support_scripts/puppet/modules/matecat/manifests/development.pp
