#!/usr/bin/env python

import sys
from collections import defaultdict
from itertools import imap, izip
from htmlentitydefs import name2codepoint
import re
from operator import itemgetter

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('-t', action='store_true', dest='tolerant',
                        help='tolerant', default=False)
    parser.add_argument('-v', action='store_true', dest='verbose',
                        help='print lines and linenr', default=False)
    args = parser.parse_args(sys.argv[1:])

    expressions = []
    if args.tolerant:
        # We are looking for damaged entities, i.e. entities where either the
        # preceding & or the succeeding ; is missing
        expressions = [re.compile("(?:[^a-zA-Z])(&%s|%s;)" %(name, name)) for name in name2codepoint.keys()]
    else:
        expressions = [re.compile("&%s;" %(name)) for name in name2codepoint.keys()]

    entity_counts = defaultdict(int)
    for linenr, line in enumerate(sys.stdin):
        line = line.strip()
        #print line
        for ex in expressions:
            match = ex.search(line)
            if match:
                entity_counts[match.group()] += 1
                if args.verbose:
                    print linenr, line

    # print entity_counts
    for entity, count in sorted(entity_counts.items(), key=itemgetter(1), reverse=True):
        print "%s %s" %(count, entity)
