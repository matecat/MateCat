#!/bin/bash

set -e
cd /vagrant
sudo puppet module install ripienaar-concat
sudo puppet module install puppetlabs-stdlib

sudo puppet apply --modulepath=tools/puppet/modules tools/puppet/manifests/development.pp
