import {formatText, getCharactersCounter} from './textUtils'

describe('formatText', () => {
  test('converts text to uppercase', () => {
    expect(formatText('hello world', 'uppercase')).toBe('HELLO WORLD')
  })

  test('converts text to lowercase', () => {
    expect(formatText('HELLO WORLD', 'lowercase')).toBe('hello world')
  })

  test('capitalizes the first letter of each word', () => {
    expect(formatText('hello world', 'capitalize')).toBe('Hello World')
  })

  test('leaves the text untouched for an unknown format', () => {
    expect(formatText('Hello World', 'unknown')).toBe('Hello World')
  })
})

describe('getCharactersCounter', () => {
  test('counts plain ASCII text one character at a time', () => {
    expect(getCharactersCounter('hello')).toBe(5)
  })

  test('expands encoded tag placeholders before counting', () => {
    expect(getCharactersCounter('foo##$_A0$##bar')).toBe('foo°bar'.length)
  })

  test('decodes html entities before counting', () => {
    expect(getCharactersCounter('a &lt;b&gt; c')).toBe('a <b> c'.length)
  })

  test('handles multi-code-unit characters via the custom matchers', () => {
    const result = getCharactersCounter('a\u{1F600}b')

    expect(typeof result).toBe('number')
    expect(result).toBeGreaterThan(0)
  })

  test('returns 0 for an empty string', () => {
    expect(getCharactersCounter('')).toBe(0)
  })
})
