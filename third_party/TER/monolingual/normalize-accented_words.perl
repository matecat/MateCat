#!/usr/bin/perl -w

binmode(STDIN, ":utf8");
binmode(STDOUT, ":utf8");

select(STDIN);$| = 1;
select(STDOUT);$| = 1;

use FindBin qw($Bin);
use strict;

my $mydir = "$Bin/normalization_rules";
#print STDERR "mydir:$mydir\n\n\n";
my %NORMALIZATION_RULES = ();
my $corpus = "";
my $QUIET = 0;
my $HELP = 0;

while (@ARGV) {
	$_ = shift;
	/^-b$/ && ($| = 1, next);
	/^-c$/ && ($corpus = shift, next);
	/^-q$/ && ($QUIET = 1, next);
	/^-h$/ && ($HELP = 1, next);
}

if ($HELP) {
	print "Usage ./normalize-accented_words.perl (-c \"[ACCENT-IT|...]+\") < textfile > normalizedfile\n";
        print "Options:\n";
        print "  -q  ... quiet.\n";
        print "  -b  ... disable Perl buffering.\n";
        print "  -c  ... list of one or more corpora name for which normalization rules are required.\n";
	exit;
}
if (!$QUIET) {
	print STDERR "Normalizer Version 1.0\n";
	print STDERR "Corpus: $corpus\n";
}

if ($corpus ne "") { $corpus="$corpus generic"; } else { $corpus="generic"; }

load_rules($corpus,\%NORMALIZATION_RULES);

if (scalar(%NORMALIZATION_RULES) eq 0){
	print STDERR "Warning: No known normalization rules for these corpora '$corpus'\n";
}

while(<STDIN>) {
	if (/^<.+>$/ || /^\s*$/) {
		#don't try to tokenize XML/HTML tag lines
		print $_;
	}
	else {
		print &normalize($_);
	}
}

sub normalize {
	my($text) = @_;
	chomp($text);
	$text = " $text ";

	foreach my $idx (sort {$a <=> $b} keys %NORMALIZATION_RULES){
		foreach my $k (keys %{ $NORMALIZATION_RULES{$idx} }){
			my $v = $NORMALIZATION_RULES{$idx}{$k};
#			next if $text =~ / $k\'/;
			$text =~ s/\b$k( |[^\'])/$v$1/g;
		}
	}

$text =~ tr/ / /;
#$text =~ tr/ /  /;
	# clean up extraneous spaces
	$text =~ s/ +/ /g;
	$text =~ s/^ //g;
	$text =~ s/ $//g;

	#ensure final line break
	$text .= "\n" unless $text =~ /\n$/;

	return $text;
}

sub load_rules {
	my ($corpuslist, $RULES_REF) = @_;

	my @corpora = split(/[ \t]+/,$corpuslist);

	for my $corpus (@corpora){
	my $rulesfile = "$mydir/normalization_rules.$corpus";
	
	#default back to generic normalization rules if we don't have a corpus-specific file
	if (!(-e $rulesfile)) {
		print STDERR "WARNING: No known normalization rules for corpus '$corpus', attempting fall-back to the generic version...\n";
		$rulesfile = "$mydir/normalization_rules.generic";
		die ("ERROR: No normalization rules files found in $mydir\n") unless (-e $rulesfile);
	}
	
	if (-e "$rulesfile") {
		print STDERR "Loading normalization rules for corpus '$corpus', attempting fall-back to the generic version...\n";
		open(RULES, "<:utf8", "$rulesfile");
		my $idx=0;
		while (<RULES>) {
			my $item = $_;
			chomp($item);
			next if $item eq "";
			my ($k,$v) = split(/[\s]+/,$item);
                        $k =~ s/_BLANK_/ /g;
                        $v =~ s/_BLANK_/ /g;
                        $v =~ s/_EMPTY_/ /g;

#			$k =~ s/_BLANK_/ /g; 
#			$v =~ s/_BLANK_/ /g; 
#			if ($v eq "_BLANK_"){ $v = " "; }
#print "RULE: |$k| -> |$v|\n";
			$RULES_REF -> {$idx} -> {$k} = $v;
			$idx++;
		}
		close(RULES);
	}
	}	
}

