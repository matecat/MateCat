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
	warn "Usage: fix_corpus_translated-tm.pl [-help] [-language=de|en|es|fr|it]\n";
	warn "	-help 	\tprint this help\n";
	warn "	-language 	\tlanguage to work on\n";
}

if (scalar(@ARGV) < $required_params || scalar(@ARGV) > ($required_params+$optional_params) || $help) {
    &Usage();
    exit;
}

my $in="";
while ($in = <STDIN>){

chomp($in);

while ($in =~ s/\{\\([\\A-z0-9]*)lang[0-9]+ ([^\{\}]*)\}/$2/g){};
while ($in =~ s/\{((\\[A-z0-9])+)([^\{\}]*?)\}/C$3D/g){};
$in =~ s/([\.\:\;\?\!]) *(\\+ +(\@\@|\#\#))+/$1_MATECAT_NEWLINE_/g;
$in =~ s/(\\+ +\#\# )(\\+ +(\@\@|\#\#))+/_MATECAT_NEWLINE_/g;
$in =~ s/([\.\:\;\?\!]) \@\@/_MATECAT_NEWLINE_/g;
$in =~ s/(_MATECAT_NEWLINE_ *)+/\n/g;
$in =~ s/(\\)+ +\@\@//g;
$in =~ s/\@\@//g;

$in =~ s/ +/ /g;
$in =~ s/ ([\,\.\;\:])/$1/g;

print "$in\n";
}

