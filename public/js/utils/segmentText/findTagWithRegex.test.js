import findTagWithRegex from './findTagWithRegex'

describe('findTagWithRegex', () => {
  test('finds all occurrences of a tag type in text', () => {
    const text = 'a <g id="1">b</g> c <g id="2">d</g> e'
    const result = findTagWithRegex(text, 'g')
    expect(result).toHaveLength(2)
    expect(result[0].data.encodedText).toBe('<g id="1">')
    expect(result[1].data.encodedText).toBe('<g id="2">')
  })

  test('populates offset, id, placeholder and originalOffset on each tag', () => {
    const text = '<bx id="9"/>'
    const result = findTagWithRegex(text, 'bx')
    expect(result).toHaveLength(1)
    const tag = result[0]
    expect(tag.offset).toBe(0)
    expect(tag.data.id).toBe('9')
    expect(tag.data.originalOffset).toBe(0)
    expect(tag.type).toBe('bx')
  })

  test('returns an empty array when no matches are found', () => {
    expect(findTagWithRegex('plain text', 'g')).toEqual([])
  })

  test('returns an empty array and does not throw for an unknown tag name', () => {
    expect(findTagWithRegex('<g id="1">x</g>', 'notATag')).toEqual([])
  })
})
