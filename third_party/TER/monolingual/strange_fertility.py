#!/usr/bin/python
import sys
from itertools import izip
from writer import write_numbers

if __name__ == "__main__":
    import argparse
    parser = argparse.ArgumentParser()
    parser.add_argument('source', action='store', )
    parser.add_argument('target', action='store')
    parser.add_argument('-d', action='store', help="max length difference",
                        type=float, default=0)
    parser.add_argument('-relative', help="difference is relative, min 1.0",
                        action='store_true')
    parser.add_argument('-v', action='store', help="verbosity (0-2)", type=int,
                        default=1)
    parser.add_argument('-min', action='store',
                        help="minimum nr of words on shorter side", type=int,
                        default=0)
    parser.add_argument('-write', action='store',
                        help="write linenr of offending lines to file")
    args = parser.parse_args(sys.argv[1:])

    lines = []
    for linenr, (src_line, tgt_line) in enumerate(izip(open(args.source), open(args.target))):
        # skip short lines
        src_line = src_line.strip().split()
        tgt_line = tgt_line.strip().split()
        l1 = len(src_line)
        l2 = len(tgt_line)

        if l1 > l2:
            l1, l2 = l2, l1

        assert l1 <= l2

        if l1 < args.min:
            continue

        if args.relative:
            rel = l2/l1
            if rel <= args.d:
                continue
        else:
            if l2-l1 <= args.d:
                continue

        lines.append(linenr)

        if args.v > 0:
            sys.stdout.write("line: %s\n" %(linenr))
        if args.v > 1:
            sys.stdout.write(" src: %s\n" %(" ".join(src_line)))
            sys.stdout.write(" tgt: %s\n" %(" ".join(tgt_line)))

    sys.stdout.write("found %s lines\n" %(len(lines)))

    if args.write:
        write_numbers(lines, args.write)
