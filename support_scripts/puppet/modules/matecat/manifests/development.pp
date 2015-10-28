group { "puppet":
  ensure => "present",
}

File { owner => 0, group => 0, mode => 0644 }

file { '/etc/motd':
  content => "Welcome to your Vagrant-built virtual machine!
              Managed by Puppet.\n"
}

exec { "apt-update":
    command => "/usr/bin/apt-get update"
}

Exec["apt-update"] -> Package <| |> # spaceship operator https://docs.puppetlabs.com/puppet/3/reference/lang_collectors.html

# development packages
package {['zsh', 'tmux', 'git-core', 'vim']:
  ensure  => installed
}

class {'nodejs':
  version => 'v0.12.4',
} -> file {'/usr/bin/node':
  ensure => 'link',
  target => '/usr/local/node/node-default/bin/node'
} -> file {'/usr/bin/npm':
  ensure => 'link',
  target => '/usr/local/node/node-default/bin/npm'
} ->
package {'grunt-cli':
  provider => npm
} -> file {'/usr/bin/grunt':
  ensure => 'link',
  target => '/usr/local/node/node-default/bin/grunt'
}

# runtime required packages

class {'matecat::php': }

file { '.env': 
  path    => '/vagrant/inc/.env',
  owner   => 'vagrant',
  group   => 'vagrant',
  content => 'development'
}

package {['redis-server', 'screen', 'postfix']:
  ensure => installed
}

# Apache config
apache::mod { 'rewrite': }
apache::mod { 'filter': }
apache::mod { 'headers': }
apache::mod { 'expires': }
apache::mod { 'proxy': }
apache::mod { 'proxy_http': }

class { 'apache':
  mpm_module    => 'prefork',
  default_vhost => false,
  user          => 'vagrant',
  group         => 'vagrant'
}

class {'::apache::mod::php':
  path => "${::apache::params::lib_path}/libphp5.so",
}

apache::vhost { 'matecat':
  default_vhost => true,
  port          => 80,
  docroot       => '/vagrant',
  docroot_owner => 'vagrant',
  docroot_group => 'www-data',
  priority      => 25,
  override      => ['All']
}

concat::fragment { "matecat-custom-fragment":
  target  => '25-matecat.conf',
  order   => 11,
  content => template('matecat/development-vhost.conf-fragment.erb')
}

class { 'java':
  distribution => 'jre',
} ->
class { 'activemq': }

activemq::instance { 'matecat':
  stomp_queue_port       => 61613,
  stomp_queue            => true,
  user_name              => 'login',
  user_password          => 'passcode',
  user_auth_queue        => '>',
  authentication_enabled => false,
  authorization_enabled  => false
}

# MateCAT config
exec { 'cp inc/config.ini.sample inc/config.ini':
  path    => '/bin',
  cwd     => '/vagrant',
  creates => '/vagrant/inc/config.ini'
}

exec { 'cp inc/oauth_config.ini.sample inc/oauth_config.ini':
  path    => '/bin',
  cwd     => '/vagrant',
  creates => '/vagrant/inc/config.ini'
}

class { '::mysql::server':
  root_password           => 'strongpassword',
  remove_default_accounts => true,
  override_options        => $override_options
}

exec { 'cat lib/Model/matecat.sql lib/Model/comments.sql > /var/tmp/matecat-schema.sql':
  path    => '/bin',
  cwd     => '/vagrant', 
  creates => '/vagrant/lib/Model/matecat-schema.sql'
} 

mysql::db { 'matecat':
  dbname   => 'matecat', 
  user     => 'matecat_user',
  password => 'matecat_user',
  host     => 'localhost',
  grant    => ['ALL'],
  sql      => '/var/tmp/matecat-schema.sql'
} 

mysql::db { 'matecat_test':
  dbname   => 'matecat_test', 
  user     => 'matecat_user',
  password => 'matecat_user',
  host     => 'localhost',
  grant    => ['ALL'],
  sql      => '/var/tmp/matecat-schema.sql'
}
