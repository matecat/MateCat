#!/usr/bin/perl -w

use Getopt::Long;

select(STDIN); $|=1;
select(STDOUT); $|=1;

# parameter variables
my $help = undef; 
my $lang = "";

# parameter definition
GetOptions(
  "help" => \$help,
  "language=s" => \$lang,
) or exit(1);

my $required_params = 0; # number of required free parameters
my $optional_params = 5; # maximum number of optional free parameters


# command description
sub Usage(){
	warn "Usage: fix_corpus_euconst.pl [-help] [-language=de|en|es|fr|it]\n";
	warn "	-help 	\tprint this help\n";
	warn "	-language 	\tlanguage to work on\n";
}

if (scalar(@ARGV) < $required_params || scalar(@ARGV) > ($required_params+$optional_params) || $help) {
    &Usage();
    exit;
}

if ($lang ne "de") {
while ($in = <STDIN>){
	print STDOUT $in;
}
exit;
}

#$*=1; #multiline matching (deprecated)
my $month="Januar|Februar|März|April|Mai|Juni|Juli|August|September|Oktober|November|Dezember";

my $in="";
my $out="";
while ($in = <STDIN>){

chomp($in);

$out .= $in."\n";
next if ($in =~ /[0-9]{1,2}\.$/);

$out =~ s/([0-9]{1,2}\.)\n($month)\b/$1 $2/g;
print STDOUT "$out";
$out = "";
}
$out =~ s/([0-9]{1,2}+\.)\n($month)\b/$1 $2/g;
print STDOUT "$out" if $out ne "";

