#!/usr/bin/env python
# -*- coding: utf-8 -*-

import sys
from collections import defaultdict
from itertools import imap, izip
import codecs
from writer import write_numbers

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('-write', action='store', help="write linenr of offending lines to file")
    parser.add_argument('-n', action='store', type=int, default=3,
                        help="maximum number of 'strange' characters (default: 3)")
    parser.add_argument('-v', action='store_true', dest='verbose',
                        help='verbose (default:off)', default=False)
    args = parser.parse_args(sys.argv[1:])

    # english standard characters
    chars = set(u"abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ")

    # numbers
    chars.update(set(u"0123456789"))

    # spaces (only regular space)
    chars.update(set(u" "))

    # general non-alphanumeric
    chars.update(set(u"-_*°:.,;?¿!¡%@\\#()[]{}<>+=|/'&\""))

    # currencies
    chars.update(set(u"$€£"))

    # bullet
    chars.update(set(u"•"))

    # intellectual property
    chars.update(set(u"©®™"))
    chars.update(set(u"†"))

    # foreign characters
    chars.update(set(u"ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ"))
#    chars.update(set(u"ñçóíîôúÚáœâàéèêüäöÉÄÜÁÀÖß"))
#Cyrillic alphabet
    chars.update(set(u"АаБбВвГгДдЕеЖжЗзИиЙйКкcasЛлlasМмНнОоПпРрСсТтУуФфХхЦцЧчШшЩщЪъЬьЮюЯя"))
    chars.update(set(u"AaÁáBbCcČčDdĎďEeÉéĚěésFfGgHhChchIiměkkéíÍídlouhéměkkéJjKkLlMmNnŇňOoÓóPpQqRrŘřSsŠšTtŤťUuÚúúsŮůVvWwXxYykrátkétvrdéÝýdlouhétvrdéZzŽž"))
#Romanian alphabet
    chars.update(set(u"AaĂăÂâBbCcDdEeFfGgHhIiÎîJjKkLlMmNnOoPpQqRrSsȘșTtȚțUuVvWwXxYyZz"))
#Polish alphabet
    chars.update(set(u"AaĄąBbCcĆćDdEeĘęFfGgHhIiJjKkLlŁłMmNnŃńOoÓóPpRrSsŚśTtUuWwYyZzŹźŻż"))
#Maltese alphabet
    chars.update(set(u"AaBbĊċDdEeFfĠġGgGħgħHhĦħIiIeieJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxŻżZz"))
#Latvian alphabet
    chars.update(set(u"aābcčdeēfgģhiījkķlļmnņoprsštuūvzžAĀBCČDEĒFGĢHIĪJKĶLĻMNŅOPRSŠTUŪVZŽ"))
#   chars.update(set(u""))
    chars.update(set(u"~"))
    chars.update(set(u"^"))
    chars.update(set(u"—"))

    # mathematical
    chars.update(set(u"½¼¾²³ⁿ±%‰‱≥≤"))
    chars.update(set(u"ΔΣ⋅∆×∑τ·"))
#    chars.update(set(u""))

    # quotation
    chars.update(set(u"“”"))

    in_stream = codecs.getreader("utf-8")(sys.stdin)   # read from stdin
    out_stream = codecs.getwriter("utf-8")(sys.stdout) # write to stdout


    strange_lines = []
    for linenr, line in enumerate(in_stream):
        line = line.strip()
        # strange_chars = set(line) - chars
        strange_chars = [c for c in line if not c in chars]
        normal_chars = [c for c in line if c in chars]
        if len(strange_chars) > args.n:
            if args.verbose:
                out_stream.write(u"line %s: %s offending characters: |%s|\n"
                                 %(linenr, len(strange_chars),u"|".join(strange_chars)))
                out_stream.write(line + u"\n")
            strange_lines.append(linenr)
            # print u" ".join(list(linenr, set(line) - chars), " orig:", line

#        out_stream.write("%s\n" %(u"".join(normal_chars)))

    sys.stdout.write("found %s lines\n" %(len(strange_lines)))
    if args.write:
        write_numbers(strange_lines, args.write)
