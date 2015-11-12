#!/bin/bash

cd /vagrant

set -e

function removeDownloads {
  rm -f puppetlabs-release-precise.deb
}

if ! ( which puppet ) ; then
  removeDownloads

  wget https://apt.puppetlabs.com/puppetlabs-release-precise.deb
  sudo dpkg -i puppetlabs-release-precise.deb
  sudo apt-get update
  sudo apt-get install -y puppet
fi
removeDownloads

sudo puppet module install ripienaar-concat
sudo puppet module install puppetlabs-stdlib

sudo puppet apply --modulepath=support_scripts/puppet/modules support_scripts/puppet/modules/matecat/manifests/development.pp
