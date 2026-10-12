import {setTagSignatureMiddleware} from './tagModel'
import matchTag from './matchTag'

setTagSignatureMiddleware('space', () => false)

test('matches a paired open/close tag and links them by a shared id', () => {
  const result = matchTag('test <g id="1">tag</g> end')

  const opening = result.find((tag) => tag.data.id === '1' && !tag.data.openTagId)
  const closing = result.find((tag) => tag.data.openTagId)

  expect(opening).toBeDefined()
  expect(closing).toBeDefined()
  expect(closing.data.openTagId).toBe(opening.data.closeTagId)
  expect(closing.data.id).toBe(opening.data.id)
})

test('marks an orphan closing tag with a "?" placeholder', () => {
  const result = matchTag('test </g> end')

  const closing = result.find((tag) => tag.data.placeholder === '?')

  expect(closing).toBeDefined()
  expect(closing.data.openTagId).toBeNull()
})

test('matches self-closing tags without pairing them', () => {
  const result = matchTag('value ##$_A0$## end')

  expect(result).toHaveLength(1)
  expect(result[0].data.closeTagId).toBeNull()
})

test('returns an empty array when there is nothing to match', () => {
  expect(matchTag('plain text')).toEqual([])
})

test('sorts multiple open and closing tags by offset', () => {
  const result = matchTag(
    '<g id="1">a</g> <g id="2">b</g> <g id="3">c</g>',
  )

  const openings = result
    .filter((tag) => !tag.data.openTagId)
    .sort((a, b) => a.offset - b.offset)

  expect(openings.map((tag) => tag.data.id)).toEqual(['1', '2', '3'])
})
