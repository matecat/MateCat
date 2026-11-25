import structFromName from './tagFromTagType'

test('builds a TagStruct for a buildable tag', () => {
  const tag = structFromName('nbsp')

  expect(tag.offset).toBe(0)
  expect(tag.length).toBe('°'.length)
  expect(tag.type).toBe('nbsp')
  expect(tag.data.encodedText).toBe('##$_A0$##')
  expect(tag.data.decodedText).toBe('°')
  expect(tag.data.placeholder).toBe('°')
  expect(tag.data.originalOffset).toBe(0)
})

test('returns null for a tag that is not buildable', () => {
  expect(structFromName('g')).toBeNull()
})
