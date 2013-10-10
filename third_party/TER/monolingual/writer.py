def write_numbers(numbers, filename, sort=False):
    if sort:
        numbers.sort()
    with open(filename, 'w') as of:
        for n in numbers:
            of.write("%s\n" %(n))
