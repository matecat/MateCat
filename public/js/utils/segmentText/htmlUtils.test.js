import {getIdAttributeRegEx, unescapeHTMLRecursive} from './htmlUtils'

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

describe('unescapeHTMLRecursive', () => {
  test('unescapes all known html entities', () => {
    expect(unescapeHTMLRecursive('&lt;p&gt;')).toBe('<p>')
    expect(unescapeHTMLRecursive('&amp;amp;')).toBe('&')
    expect(unescapeHTMLRecursive('&amp;')).toBe('&')
    expect(unescapeHTMLRecursive('&nbsp;')).toBe(' ')
    expect(unescapeHTMLRecursive('&apos;')).toBe("'")
    expect(unescapeHTMLRecursive('&quot;')).toBe('"')
  })

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
