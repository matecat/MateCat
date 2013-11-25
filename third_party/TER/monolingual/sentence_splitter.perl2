#!/usr/bin/perl -w

# $Id: sentence_splitter.perl $
# Sample Sentence Splitter
# written by Nicola Bertoldi, based on code by Philipp Koehn (tokenizer.perl)

binmode(STDIN, ":utf8");
binmode(STDOUT, ":utf8");

use FindBin qw($Bin);
use strict;
#use Time::HiRes;

my $mydir = "$Bin/nonbreaking_prefixes";

my %NONBREAKING_PREFIX = ();
my $language = "en";
my $QUIET = 0;
my $HELP = 0;
my $AGGRESSIVE = 0;

my $punct = "[\.\?\!]";
my $quotsymbols = "[\"\']";

#my $start = [ Time::HiRes::gettimeofday( ) ];

while (@ARGV) {
	$_ = shift;
	/^-b$/ && ($| = 1, next);
	/^-l$/ && ($language = shift, next);
	/^-q$/ && ($QUIET = 1, next);
	/^-h$/ && ($HELP = 1, next);
	/^-a$/ && ($AGGRESSIVE = 1, next);
}

if ($HELP) {
	print "Usage ./tokenizer.perl (-l [en|de|...]) < textfile > tokenizedfile\n";
        print "Options:\n";
        print "  -q  ... quiet.\n";
        print "  -a  ... aggressive hyphen splitting.\n";
        print "  -b  ... disable Perl buffering.\n";
	exit;
}
if (!$QUIET) {
	print STDERR "Sentence Splitter Version 1.0\n";
	print STDERR "Language: $language\n";
}

load_prefixes($language,\%NONBREAKING_PREFIX);

if (scalar(%NONBREAKING_PREFIX) eq 0){
	print STDERR "Warning: No known abbreviations for language '$language'\n";
}

while(<STDIN>) {

#print STDERR "tokenizer.pl READ:$_";

	if (/^<.+>$/ || /^\s*$/) {
		#don't try to tokenize XML/HTML tag lines
		print $_;
	}
	else {
		print &splitter($_);
	}
#print STDERR "tokenizer.pl PRINT:$_";
}

#my $duration = Time::HiRes::tv_interval( $start );
#print STDERR ("EXECUTION TIME: ".$duration."\n");


sub splitter {
	my($text) = @_;
	chomp($text);
	$text = " $text ";

	#word token method
	my @words = split(/[\ \t\r\n\f]/,$text);
	$text = "";
	for (my $i=0;$i<(scalar(@words));$i++) {
		my $word = $words[$i];
		if ( $word =~ /^(\S+)($punct$quotsymbols?)$/) {
			my $pre = $1;
			my $pnct = $2;
			if (($pre =~ /\./ && $pre =~ /\p{IsAlpha}/) || ($NONBREAKING_PREFIX{$pre} && $NONBREAKING_PREFIX{$pre}==1) || ($i<scalar(@words)-1 && ($words[$i+1] =~ /^[\p{IsLower}]/))) {
				#no change
			} elsif (($NONBREAKING_PREFIX{$pre} && $NONBREAKING_PREFIX{$pre}==2) && ($i<scalar(@words)-1 && ($words[$i+1] =~ /^[0-9]+/))) {
				#no change
			} else {
				$word = $pre."$pnct\n";
			}
		}
		$text .= $word." ";
	}		

	# clean up extraneous spaces
	$text =~ s/ +/ /g;
	$text =~ s/^ //g;
	$text =~ s/ $//g;
	$text =~ s/\n /\n/g;

	#ensure final line break
	$text .= "\n" unless $text =~ /\n$/;

	return $text;
}

sub load_prefixes {
	my ($language, $PREFIX_REF) = @_;
	
	my $prefixfile = "$mydir/nonbreaking_prefix.$language";
	
	#default back to English if we don't have a language-specific prefix file
	if (!(-e $prefixfile)) {
		$prefixfile = "$mydir/nonbreaking_prefix.en";
		print STDERR "WARNING: No known abbreviations for language '$language', attempting fall-back to English version...\n";
		die ("ERROR: No abbreviations files found in $mydir\n") unless (-e $prefixfile);
	}
	
	if (-e "$prefixfile") {
		open(PREFIX, "<:utf8", "$prefixfile");
		while (<PREFIX>) {
			my $item = $_;
			chomp($item);
			if (($item) && (substr($item,0,1) ne "#")) {
				if ($item =~ /(.*)[\s]+(\#NUMERIC_ONLY\#)/) {
					$PREFIX_REF->{$1} = 2;
				} else {
					$PREFIX_REF->{$item} = 1;
				}
			}
		}
		close(PREFIX);
	}
}

