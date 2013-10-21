#!/usr/bin/perl -w

use MateCat;
use Getopt::Long;

# parameter variables
my $help = undef; 
my $c = 0; 
my $MAX_TOKENS=0;

select(STDIN); $|=1;
select(STDOUT); $|=1;

# parameter definition
GetOptions(
  "help" => \$help,
  "count" => \$count,
  "limit=i" => \$MAX_TOKENS,
) or exit(1);

my $required_params = 0; # number of required free parameters
my $optional_params = 0; # maximum number of optional free parameters

# command description
sub Usage(){
        warn "Usage: split_into_segment.pl [-help] [-count]\n";
        warn "  -help   \tprint this help\n";
        warn "  -count   \tprint the number of split performed for each input line, before printing splits\n";
        warn "  -limit   \tnumber of words for which splitting is not tried at all\n";
}

if (scalar(@ARGV) < $required_params || scalar(@ARGV) > ($required_params+$optional_params) || $help) {
    &Usage();
    exit;
}
my $punct = "[\.\;]";
my $symbols = "[»\"\'i]";
my $in="";
while($in=<STDIN>){
	my @tokens = split(/[ \t]+/,$in);
#	print STDERR " tokens: ",scalar(@tokens),"\n";
	if (scalar(@tokens) > $MAX_TOKENS){
	   	$in =~ s/ ($punct$symbols?) / $1\n/g;
	}
	if ($count){
		my @l= split (/\n/,$in);
		print STDOUT scalar(@l),"\n";
#	        print STDERR " split.pl printing $in";
	}
	print STDOUT "$in";
}

