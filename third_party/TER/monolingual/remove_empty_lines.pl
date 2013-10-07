#!/usr/bin/perl

use strict;

select(STDOUT); $|=1;

while (my $line=<STDIN>){
	print STDOUT $line if $line !~ /^[ \t]*$/;
}


