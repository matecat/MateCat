import {regexWordDelimiter} from './textConstants'

test('regexWordDelimiter matches common word-delimiting characters', () => {
  expect('hello world'.split(regexWordDelimiter)).toEqual([
    'hello',
    ' ',
    'world',
  ])
})

test('regexWordDelimiter matches punctuation and digits as delimiters', () => {
  expect('a1b'.split(regexWordDelimiter)).toEqual(['a', '1', 'b'])
  expect('a-b'.split(regexWordDelimiter)).toEqual(['a', '-', 'b'])
})
