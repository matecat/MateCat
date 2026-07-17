import {EditorState, ContentState} from 'draft-js'

import transformLexiqaPoints from './transformLexiqaPoints'

const editorStateFromText = (text) =>
  EditorState.createWithContent(ContentState.createFromText(text))

test('keeps the offsets unchanged for a warning inside a single block', () => {
  const editorState = editorStateFromText('Hello world')

  const result = transformLexiqaPoints(editorState, 0, 5)

  expect(result).toEqual({start: 0, end: 5})
})

test('returns an empty result when the warning offsets are out of range', () => {
  const editorState = editorStateFromText('Hello world')

  const result = transformLexiqaPoints(editorState, 100, 105)

  expect(result).toEqual({})
})

test('rebases the offsets relative to the block that contains the warning', () => {
  const editorState = editorStateFromText('Hello world\nGoodbye now')

  // "Goodbye" starts right after the first block (11 chars) plus the
  // implicit newline character accounted for by the block map traversal.
  const result = transformLexiqaPoints(editorState, 12, 19)

  expect(result).toEqual({start: 0, end: 7})
})
