import {EditorState, ContentState, SelectionState, Modifier} from 'draft-js'

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

test('shifts the offsets back by 2 for each non-lexiqa entity preceding the warning', () => {
  let contentState = ContentState.createFromText('ABtagCDEF')
  contentState = contentState.createEntity('TAG', 'IMMUTABLE', {name: 'g'})
  const entityKey = contentState.getLastCreatedEntityKey()
  const blockKey = contentState.getFirstBlock().getKey()
  contentState = Modifier.applyEntity(
    contentState,
    SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 2,
      focusOffset: 5,
    }),
    entityKey,
  )
  const editorState = EditorState.createWithContent(contentState)

  const result = transformLexiqaPoints(editorState, 6, 8)

  expect(result).toEqual({start: 4, end: 6})
})

test('adjusts a warning that starts on the newline joining two blocks', () => {
  const editorState = editorStateFromText('ab\ncd')

  const result = transformLexiqaPoints(editorState, 2, 3)

  expect(result).toEqual({start: 0, end: 0})
})
