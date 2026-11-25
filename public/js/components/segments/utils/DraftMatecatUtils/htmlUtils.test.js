import {
  getIdAttributeRegEx,
  unescapeHTMLinTags,
  unescapeHTMLRecursive,
} from './htmlUtils'

describe('getIdAttributeRegEx', () => {
  test('matches an id attribute and captures its value', () => {
    const regex = getIdAttributeRegEx()
    const match = regex.exec('<g id="mtc_1">')
    expect(match[1]).toBe('mtc_1')
  })

  test('matches negative numeric ids', () => {
    const regex = getIdAttributeRegEx()
    const match = regex.exec('<g id="-1">')
    expect(match[1]).toBe('-1')
  })

  test('returns a new regex instance on each call (global flag safe)', () => {
    const regexA = getIdAttributeRegEx()
    const regexB = getIdAttributeRegEx()
    expect(regexA).not.toBe(regexB)
  })
})

describe('unescapeHTMLinTags', () => {
  test('unescapes all known html entities', () => {
    expect(unescapeHTMLinTags('&lt;p&gt;')).toBe('<p>')
    expect(unescapeHTMLinTags('&amp;amp;')).toBe('&')
    expect(unescapeHTMLinTags('&amp;')).toBe('&')
    expect(unescapeHTMLinTags('&nbsp;')).toBe(' ')
    expect(unescapeHTMLinTags('&apos;')).toBe("'")
    expect(unescapeHTMLinTags('&quot;')).toBe('"')
  })

  test('leaves plain text unchanged', () => {
    expect(unescapeHTMLinTags('plain text')).toBe('plain text')
  })

  test('returns empty string when replace throws (non-string input)', () => {
    expect(unescapeHTMLinTags(null)).toBe('')
    expect(unescapeHTMLinTags(undefined)).toBe('')
  })
})

describe('unescapeHTMLRecursive', () => {
  test('unescapes nested/doubly-escaped entities', () => {
    expect(unescapeHTMLRecursive('&amp;lt;p&amp;gt;')).toBe('<p>')
  })

  test('leaves text without entities unchanged', () => {
    expect(unescapeHTMLRecursive('plain text')).toBe('plain text')
  })

  test('returns input unchanged when regex.exec throws (non-string input)', () => {
    expect(unescapeHTMLRecursive(null)).toBe(null)
    expect(unescapeHTMLRecursive(undefined)).toBe(undefined)
  })
})
