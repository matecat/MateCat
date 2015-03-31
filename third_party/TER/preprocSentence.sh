#!/bin/bash

_pwd=$1
shift
lang=$1
shift
sentence=$@

pp=$_pwd/monolingual/tokenizer.perl
acc=$_pwd/monolingual/accents.pl

#echo $lang
#echo $sentence
#cd $wdir
#for lang in it en
#do
#ls EP*.${lang} |\
#while read pfn; do
#fn=`basename $pfn .${lang}`
#echo $fn $lang
#cat $pfn | perl -pe 's/▌|<BR\/>|█//g' |\

echo $sentence | perl -pe 's/▌|<BR\/>|█//g' |\
perl -pe 's/…/.../g' |\
perl -pe 's/1°/1o/g' |\
perl -pe 's/n°/n./g' |\
perl $pp -l ${lang} | perl $acc -l ${lang} | perl $acc -l ${lang} #> ${fn}.tok.${lang}
#done
#done






