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
	warn "Usage: fix_corpus_opus-php.pl [-help] [-language=de|en|es|fr|it]\n";
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

#English side
if ($lang  eq  "en"){	$in =~ s/([A-z])\' (s|t|d|ve|ll|re) /$1\'$2 /g; }
if ($lang  eq  "it"){	$in =~ s/ +\'/\'/g; }

#print "YYY:$in\n";
while ($in =~ s/\/ +([0-9A-z\_\-]+(\.[0-9A-z]+)*)/\/$1/){};
#print "XXX:$in\n";

while ($in =~ s/([0-9A-z\_\-]+(\.[0-9A-z\_\-]+)*) +\//$1\//){};
#print "ZZZ:$in\n";

$in =~ s/ ([^\/]+)\/(usr)/ $1 \/$2/g;
#print "WWW:$in\n";


my $start = "news|ftp|http|ftps|https";
$in =~ s/($start):( *\/)+/$1:\/\//g;

while ($in =~ s/(($start):.+?\/) +(.+?\/)/$1$3/){};

$in =~ s/(($start):.+?\/) (\~[A-z0-9]+|([A-z0-9]+(\.[0-9A-z]+)+))/$1$3/;

$out = "";
while ($in =~ /" *([^"]+?) *"/){ $out .= $`; $out .= " \"$1\" "; $in = $';}
$in = $out.$in;

$out = "";
while ($in =~ /(\& [^;]+?;)/){ $out .= $`; my $tmp=$1; $in = $'; $tmp =~ s/ //g; $out .= " $tmp "; }
$in = $out.$in;

$in =~ s/([A-z0-9]+\.) +([A-z0-9]+\@)/$1$2/g;
$domainlist="org|net|com|it|de|cz|dk|il|at|fr|uk|es|pt";
while ($in =~ s/([A-z0-9]+\.) +(([A-z0-9]+\.)*($domainlist))/$1$2/) { };

$in =~ s/ *= *\//=\//g;

$out = "";
if ($lang eq "en"){ while ($in =~ /' *([^']+?) *'/ && $in !~ /'[dst] /){ $out .= $`; $out .= " '$1' "; $in = $';} }
if ($lang eq "it"){ while ($in =~ /' *([^']+?) *'/ && $in !~ /[dln]'/i){ $out .= $`; $out .= " '$1' "; $in = $';} }
if ($lang eq "fr"){ $in =~ s/jusqu '/jusqu'/gi; while ($in =~ /' *([^']+?) *'/ && $in !~ /[dln]'/i){ $out .= $`; $out .= " '$1' "; $in = $';} }
if ($lang eq "de"){ while ($in =~ /' *([^']+?) *'/){ $out .= $`; $out .= " '$1' "; $in = $';} }
$in = $out.$in;

$in =~ s/\?php\/ \//\?php \/ \/ /g;
$in =~ s/\( +\"(.+?)\" +\)/\(\"$1\"\)/g;
$in =~ s/\[ +\"(.+?)\" +\]/\[\"$1\"\]/g;

$in =~ s/ +/ /g;
$in =~ s/ ([\,\.\;\:])/$1/g;

print "$in\n";
}

