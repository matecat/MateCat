#!/usr/bin/python
import sys
from itertools import izip
from writer import write_numbers


def levenshtein(s1, s2):
    # from wikipedia
    if len(s1) < len(s2):
        return levenshtein(s2, s1)
    if not s1:
        return len(s2)

    previous_row = range(len(s2) + 1)
    for i, c1 in enumerate(s1):
        current_row = [i + 1]
        for j, c2 in enumerate(s2):
            insertions = previous_row[j + 1] + 1 # j+1 instead of j since previous_row and current_row are one character longer
            deletions = current_row[j] + 1       # than s2
            substitutions = previous_row[j] + (c1 != c2)
            current_row.append(min(insertions, deletions, substitutions))
        previous_row = current_row

    return previous_row[-1]


if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('source', action='store', )
    parser.add_argument('target', action='store')
    parser.add_argument('-d', action='store', help="max levenshtein distance",
                        type=int, default=0)
    parser.add_argument('-min', action='store', help="minimum length", type=int,
                        default=0)
    parser.add_argument('-v', action='store', help="verbosity (0-2)", type=int,
                        default=1)
    parser.add_argument('-write', action='store',
                        help="write linenr of offending lines to file")
    args = parser.parse_args(sys.argv[1:])

    lines = []
    for linenr, (src_line, tgt_line) in enumerate(izip(open(args.source), open(args.target))):
        src_line = src_line.strip().split()
        tgt_line = tgt_line.strip().split()
        src_len = len(src_line)
        tgt_len = len(tgt_line)

        # skip short lines
        if args.min >= max(src_len, tgt_len):
            continue
        #if args.min >= src_len and args.min >= tgt_len:
        #    continue

        dist = 0
        if src_line != tgt_line:
            if args.d == 0:
                continue
            else:
                dist = levenshtein(src_line, tgt_line)
                if dist > args.d:
                    continue

        lines.append(linenr)

        if args.v > 0:
            sys.stdout.write("line: %s (distance: %s)\n" %(linenr, dist))
        if args.v > 1:
            sys.stdout.write(" src: %s\n" %(" ".join(src_line)))
            sys.stdout.write(" tgt: %s\n" %(" ".join(tgt_line)))

    sys.stdout.write("found %s lines\n" %(len(lines)))

    if args.write:
        write_numbers(lines, args.write)
