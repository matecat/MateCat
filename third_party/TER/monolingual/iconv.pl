#!/usr/bin/perl

use strict;
use Encode;
use Getopt::Long "GetOptions";

select(STDOUT); $|=1;

my $from = "UTF8";
my $to = "UTF8";
my $errorflag = undef;

&GetOptions('f=s' => \$from,
           't=s' => \$to,
           'c' => \$errorflag,
);

my $cflag=Encode::FB_DEFAULT;
$cflag=Encode::FB_QUIET if $errorflag;

while (my $line=<STDIN>){
	if (Encode::from_to($line, $from, $to, $cflag)){
		print STDOUT $line;
	}else{ # encode from $from to $to   does not work properly, try with from="latin1"
		Encode::from_to($line, "latin1", $to, $cflag);
		print STDOUT $line;
	
	}
}


