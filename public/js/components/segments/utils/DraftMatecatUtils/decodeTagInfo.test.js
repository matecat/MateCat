import decodeTagInfo from './decodeTagInfo'

describe('decodeTagInfo', () => {
  test('decodes a base64-encoded ph tag content and id', () => {
    // "hello" base64-encoded
    const tag = {
      data: {
        name: 'ph',
        encodedText:
          '<ph id="mtc_1" ctype="x-original_pc_open" equiv-text="base64:aGVsbG8="/>',
      },
    }
    const result = decodeTagInfo(tag)
    expect(result.id).toBe('mtc_1')
    expect(result.content).toBe('hello')
  })

  test('decodes a non-decodeNeeded g tag, content equals captured id group', () => {
    const tag = {
      data: {
        name: 'g',
        encodedText: '<g id="2">',
      },
    }
    const result = decodeTagInfo(tag)
    expect(result.id).toBe('2')
    expect(result.content).toBe('2')
  })

  test('unescapes html entities and strips newlines from decoded content', () => {
    // base64 for "&lt;p&gt;\n"
    const encoded = Buffer.from('&lt;p&gt;\n').toString('base64')
    const tag = {
      data: {
        name: 'ph',
        encodedText: `<ph id="mtc_2" ctype="x-html" equiv-text="base64:${encoded}"/>`,
      },
    }
    const result = decodeTagInfo(tag)
    // the trailing newline is stripped during base64 decoding, before the
    // unescape/space-replace step ever sees it
    expect(result.content).toBe('<p>')
  })

  test('falls back to placeholder when tag has no placeholderRegex', () => {
    const tag = {
      data: {
        name: 'lineFeed',
        encodedText: '##$_0A$##',
      },
    }
    const result = decodeTagInfo(tag)
    expect(result.content).toBe('\n')
    expect(result.id).toBe('')
  })

  test('returns "?" content for an unknown tag name', () => {
    const tag = {data: {name: 'notATag', encodedText: 'anything'}}
    const result = decodeTagInfo(tag)
    expect(result.content).toBe('?')
    expect(result.id).toBe('')
  })

  test('handles a tag with no data gracefully', () => {
    const result = decodeTagInfo({})
    expect(result.content).toBe('?')
  })
})
