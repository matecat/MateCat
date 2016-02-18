class matecat::nodejs { 

  exec { "install nodejs repositories":
    command => "curl -sL https://deb.nodesource.com/setup_5.x | sudo -E bash -",
    path    => '/usr/bin'
  } ->


  package { 'nodejs': 
    ensure => installed
  } ->
  exec {"install grunt globally":
    command => "npm install -g --no-progress grunt-cli",
    path    => '/usr/bin'
  }

}

include matecat::nodejs 
