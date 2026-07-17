import {createIcuTokens, isEqualICUTokens} from './createICUDecorator'

// Mock updateOffsetBasedOnEditorState to return tokens as-is (no Draft.js needed)
jest.mock(
  './updateOffsetBasedOnEditorState',
  () => (editorState, tokens) => tokens,
)

// Minimal editorState mock
const mockEditorState = {
  getCurrentContent: () => ({
    getBlockMap: () => ({
      forEach: () => {},
      toArray: () => [],
    }),
    getFirstBlock: () => ({getKey: () => 'block-1'}),
  }),
}

// ─── createIcuTokens ─────────────────────────────────────────────────────────

describe('createIcuTokens', () => {
  test('parses a simple variable token', () => {
    const result = createIcuTokens('Hello {name}', mockEditorState, 'en')
    const variableToken = result.find((t) => t.text === 'name')
    expect(variableToken).toBeDefined()
    expect(variableToken.type).toBe('id')
  })

  test('returns a text token for plain text', () => {
    const result = createIcuTokens('Hello world', mockEditorState, 'en')
    const textToken = result.find((t) => t.type === 'text')
    expect(textToken).toBeDefined()
    expect(textToken.text).toBe('Hello world')
  })

  test('returns an error token for invalid ICU syntax', () => {
    // Missing closing brace
    const result = createIcuTokens('{name', mockEditorState, 'en')
    const errorToken = result.find((t) => t.type === 'error')
    expect(errorToken).toBeDefined()
  })

  test('returns tokens with correct start/end offsets', () => {
    const result = createIcuTokens('{name}', mockEditorState, 'en')
    let offset = 0
    result.forEach((token) => {
      expect(token.start).toBe(offset)
      expect(token.end).toBe(offset + token.text.length)
      offset += token.text.length
    })
  })

  test('marks plural block with error when locale is missing required categories', () => {
    // 'en' requires 'one' and 'other' — omitting 'other' should cause an error
    const result = createIcuTokens(
      '{count, plural, one {# item}}',
      mockEditorState,
      'en',
    )
    const errorToken = result.find((t) => t.type === 'error')
    expect(errorToken).toBeDefined()
    expect(errorToken.message.some((m) => m.includes('other'))).toBe(true)
  })

  test('marks plural block with error for invalid category', () => {
    const result = createIcuTokens(
      '{count, plural, invalid {# items} other {# items}}',
      mockEditorState,
      'en',
    )
    const errorToken = result.find((t) => t.type === 'error')
    expect(errorToken).toBeDefined()
    expect(errorToken.message.some((m) => m.includes('invalid'))).toBe(true)
  })

  test('marks plural block as valid when all required categories are present', () => {
    const result = createIcuTokens(
      '{count, plural, one {# item} other {# items}}',
      mockEditorState,
      'en',
    )
    const errorToken = result.find((t) => t.type === 'error')
    expect(errorToken).toBeUndefined()
  })

  test('handles select type correctly without errors', () => {
    const result = createIcuTokens(
      '{gender, select, male {He} female {She} other {They}}',
      mockEditorState,
      'en',
    )
    const errorToken = result.find((t) => t.type === 'error')
    expect(errorToken).toBeUndefined()
  })

  test('handles nested plural inside select', () => {
    const result = createIcuTokens(
      '{gender, select, male {{count, plural, one {# item} other {# items}}} other {other}}',
      mockEditorState,
      'en',
    )
    expect(result).toBeDefined()
    expect(result.length).toBeGreaterThan(0)
  })
})

// ─── isEqualICUTokens ────────────────────────────────────────────────────────

describe('isEqualICUTokens', () => {
  test('returns true for two identical token arrays', () => {
    const tokens = [
      {type: 'id', text: 'name', start: 1, end: 5},
      {type: 'plural', text: 'plural', start: 6, end: 12},
    ]
    expect(isEqualICUTokens(tokens, tokens)).toBe(true)
  })

  test('returns false for different token arrays', () => {
    const tokensA = [{type: 'id', text: 'name', start: 1, end: 5}]
    const tokensB = [{type: 'id', text: 'count', start: 1, end: 6}]
    expect(isEqualICUTokens(tokensA, tokensB)).toBe(false)
  })

  test('ignores tokens of type "text" when comparing', () => {
    const sharedIdToken = {type: 'id', text: 'name', start: 7, end: 11}
    const tokensA = [
      {type: 'text', text: 'Hello ', start: 0, end: 6},
      sharedIdToken,
    ]
    const tokensB = [
      {type: 'text', text: 'Different text', start: 0, end: 14},
      sharedIdToken,
    ]
    expect(isEqualICUTokens(tokensA, tokensB)).toBe(true)
  })

  test('returns true for two empty arrays', () => {
    expect(isEqualICUTokens([], [])).toBe(true)
  })

  test('returns false when one array has extra non-text tokens', () => {
    const tokensA = [{type: 'id', text: 'name', start: 1, end: 5}]
    const tokensB = [
      {type: 'id', text: 'name', start: 1, end: 5},
      {type: 'plural', text: 'plural', start: 6, end: 12},
    ]
    expect(isEqualICUTokens(tokensA, tokensB)).toBe(false)
  })

  test('returns true when arrays differ only in text tokens', () => {
    const tokensA = [{type: 'text', text: 'foo', start: 0, end: 3}]
    const tokensB = [{type: 'text', text: 'bar', start: 0, end: 3}]
    expect(isEqualICUTokens(tokensA, tokensB)).toBe(true)
  })
})
