#!/usr/bin/perl -w

# correction of accents
# written by Nicola Bertoldi

select(STDIN); $|=1;
select(STDOUT); $|=1;

use FindBin qw($Bin);
use strict;

my %eng_accent = ();
my %eng_correction = (
" ' s "," 's ",
" ' re "," 're ",
" d ' "," d' ",
" D ' "," D' ",
"n ' t ","n' t ",
" o ' clock "," o'clock "
);

my %fre_accent = ();
my %fre_correction = (
" c ' ", " c' ",
" C ' ", " C' ",
" jusqu ' ", " jusqu'",
" Jusqu ' ", " Jusqu'",
" jusqu' ", " jusqu'",
" Jusqu' ", " Jusqu'",
" lorsqu ' ", " lorsqu'",
" Lorsqu ' ", " Lorsqu'",
" lorsqu' ", " lorsqu'",
" Lorsqu' ", " Lorsqu'",
" qu ' ", " qu' ",
" s ' "," s' "
);

my %ita_accent = (
"nche'","nché",
"rche'","rché",
"e'","è",
"o'","ò",
"u'","ù",
"i'","ì",
"a'","à",
"A'","À",
"E'","È",
"I'","Ì",
"O'","Ò",
"U'","Ù",
);

my %ita_correction = (
"á","à",
"í","ì",
"ó","ò",
"ú","ù",
"chè","ché",
#"l' ","l'",
" é "," è ",
" é\$"," è\$",
" É "," È ",
" É\$"," È\$",
"lunedi","lunedì",
"martedi","martedì",
"mercoledi","mercoledì",
"giovedi","giovedì",
"venerdi","venerdì",
"Lunedi","Lunedì",
"Martedi","Martedì",
"Mercoledi","Mercoledì",
"Giovedi","Giovedì",
"Venerdi","Venerdì",
"altr ' ", "altr' ",
" be ' ", " be' ",
" Be ' ", " Be' ",
" buon ' ora ", " buonora ",
" com ' ", " com' ",
" Com ' ", " Com' ",
"cos ' ", "cos' ",
" Cos ' ", " Cos' ",
" c ' ", " c' ",
" C ' ", " C' ",
"ch ' ", "ch' ",
" d ' ", " d' ",
" D ' ", " D' ",
" dov ' ", " dov' ",
" Dov ' ", " Dov' ",
"ev ' ", "ev' ",
" E ' ", " È ",
"foss ' ", "foss' ",
" fors ' ", " fors' ",
" Fors ' ", " Fors' ",
" grand ' ", " grand' ",
" Grand ' ", " Grand' ",
" J ' accuse ", " J' accuse ",
" j ' accuse ", " j' accuse ",
"ll ' ", "ll' ",
" l ' ", " l' ",
"LL ' ", "LL' ",
" L ' ", " L' ",
" m ' ", " m' ",
" M ' ", " M' ",
" mezz ' ", " mezz' ",
" Mezz ' ", " Mezz' ",
" mo ' ", " mo' ",
" n ' ", " n' ",
"nt ' ", "nt' ",
"om ' ", "om' ",
"ott ' ", "ott' ",
" po ' ", " po' ",
" pò ", " po' ",
" poc ' ", " poc' ",
" Poc ' ", " Poc' ",
" prim ' ", " prim' ",
" Prim ' ", " Prim' ",
" quand ' ", " quand' ",
" Quand ' ", " Quand' ",
" quattr ' ", " quattr' ",
" Quattr ' ", " Quattr' ",
" quell ' ", " quell' ",
" Quell ' ", " Quell' ",
" quest ' ", " quest' ",
" Quest ' ", " Quest' ",
" ' s ", " 's ",
" s ' ", " s' ",
" S ' ", " S' ",
" second ' ", " second' ",
" senz ' ", " senz' ",
" Senz ' ", " Senz' ",
" sin ' ora ", " sinora ",
" sott ' ", " sott' ",
" Sott ' ", " Sott' ",
"tim ' ", "tim' ",
" tutt ' ", " tutt' ",
" Tutt ' ", " Tutt' ",
"un ' ", "un' ",
" Un ' ", " Un' ",
" v ' ", " v' ",
" V ' ", " V' "
);

my %ger_accent = (
"ß","ss",
"ä","ae",
"ö","oe",
"ü","ue",
"Ä","Ae",
"Ö","Oe",
"Ü","Ue"
);
my %ger_correction = (
" d ' ", " d' ",
" D ' ", " D' ",
"ll ' ", "ll' ",
" l ' ", " l' ",
"LL ' ", "LL' ",
" L ' ", " L' "
);

my %general_accent = ();
my %general_correction = (
"–","-",
"’","'",
"‘","'",
"´","'",
"”","\"",
"“","\"",
"»","\"",
"«","\""
);

my %ACCENT = ();
my %CORRECT = ();
my $language = "en";
my $QUIET = 0;
my $HELP = 0;


while (@ARGV) {
	$_ = shift;
	/^-l$/ && ($language = shift, next);
	/^-q$/ && ($QUIET = 1, next);
	/^-h$/ && ($HELP = 1, next);
}

if ($HELP) {
	print "Usage ./accent.perl (-l [en|de|it|...]) < textfile > accentfile\n";
	exit;
}
#if (!$QUIET) {
#	print STDERR "Accent v1\n";
#	print STDERR "Language: $language\n";
#}

if ($language eq "en") {
	%ACCENT = %eng_accent;
	%CORRECT = %eng_correction;
} elsif ($language eq "de") {
	%ACCENT = %ger_accent;
	%CORRECT = %ger_correction;
} elsif ($language eq "it") {
	%ACCENT = %ita_accent;
	%CORRECT = %ita_correction;
} elsif ($language eq "fr") {
	%ACCENT = %fre_accent;
	%CORRECT = %fre_correction;
} else{
#	print STDERR "language: $language is unknown; using only language-independent rules\n";
}
foreach my $k (keys %general_correction){ $CORRECT{$k}=$general_correction{$k}; };
foreach my $k (keys %general_accent){ $ACCENT{$k}=$general_accent{$k}; };

my $ACCENT_STRING = join("|",keys %ACCENT);
my $CORRECT_STRING = join("|",keys %CORRECT);

print STDERR "ACCENTS:$ACCENT_STRING\n" if $QUIET ;
print STDERR "CORRECT:$CORRECT_STRING\n" if $QUIET ;

while(<STDIN>) {
	if (/^<.+>$/ || /^\s*$/) {
		#don't try to tokenize XML/HTML tag lines
		print $_;
	}
	else {
		print &accent($_);
	}
}

sub accent {
	my($text) = @_;
	chomp($text);
	$text = " $text ";
	
	$text =~ s/($ACCENT_STRING)/$ACCENT{$1}/eg if $ACCENT_STRING ne "";
	$text =~ s/($CORRECT_STRING)/$CORRECT{$1}/eg if $CORRECT_STRING ne "";

        # clean up extraneous spaces
        $text =~ s/ +/ /g;
        $text =~ s/^ //g;
        $text =~ s/ $//g;

        #ensure final line break
        $text .= "\n" unless $text =~ /\n$/;

	return $text;
}

exit;

#
# END OF FILE
#
