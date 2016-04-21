#!/bin/bash

cd /vagrant

function removeDownloads {
  rm -f puppetlabs-release-precise.deb
}

CURRENT_VERSION=$(puppet --version | grep -E "^3\.")
if test "$CURRENT_VERSION" = ""  ; then
  removeDownloads

  wget https://apt.puppetlabs.com/puppetlabs-release-precise.deb
  sudo dpkg -i puppetlabs-release-precise.deb
  sudo apt-get update
  sudo apt-get install -y puppet

  # Install base modules
  sudo puppet module install ripienaar-concat
  sudo puppet module install puppetlabs-stdlib
fi
removeDownloads


sudo puppet apply --modulepath=support_scripts/puppet/modules support_scripts/puppet/modules/matecat/manifests/development.pp
