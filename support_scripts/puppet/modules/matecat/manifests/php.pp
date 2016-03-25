class matecat::php { 

  $packages = ['php5-cli', 'php5',
  'php5-mysql', 'php5-common', 'php5-curl',
  'php-pear' ] 

  package {$packages:
    ensure => installed
  } ->
  file { 'apache php.ini':
    path    => '/etc/php5/apache2/php.ini',
    source  => 'puppet:///modules/matecat/php.ini',
    owner   => 'root',
    group   => 'root',
    notify  => Service["apache2"]
  } ->
  file { 'cli php.ini':
    path    => '/etc/php5/cli/php.ini',
    source  => 'puppet:///modules/matecat/php.ini',
    owner   => 'root',
    group   => 'root',
    notify  => Service["apache2"]
  }

}
