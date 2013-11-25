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

#$*=1; #multiline matching (deprecated)

my $in="";
my $out="";
while ($in = <STDIN>){

chomp($in);

$in =~ s/ / /g;
$in =~ s/((\D{3,}|\d{3,})\.)(\d+\.)/$1 $3/g;

$in =~ s/([0-9]+) (\%)/$1$2/g;
$in =~ s/ +/ /g;
$in =~ s/ ([\,\.\;\:])/$1/g;

$out .= $in."\n";
next if ($in !~ /[\.\;\:\?\!]$/);

$out =~ s/\(\s*(\d+)\s*\)/ \($1\)/g;
$out =~ s/(\s+|^)(\d+)\s*\)/$1$2\)/g;
$out =~ s/^\s+//;

print STDOUT "$out" if $out ne "";
$out = "";
}
$out =~ s/\(\s*(\d+)\s*\)/ \($1\)/g;
$out =~ s/(\s+|^)(\d+)\s*\)/$1$2\)/g;
$out =~ s/^\s+//;
print STDOUT "$out" if $out ne "";

