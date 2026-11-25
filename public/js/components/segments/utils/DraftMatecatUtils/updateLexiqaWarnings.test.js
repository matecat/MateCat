import {EditorState, ContentState} from 'draft-js'
// NOTE: updateLexiqaWarnings.js is byte-identical to updateOffsetBasedOnEditorState.js
// and is not imported anywhere in the app (dead code left over from a refactor).
// It is still exercised here so it is not silently left untested.
import updateLexiqaWarnings from './updateLexiqaWarnings'

const editorStateFromText = (text) =>
  EditorState.createWithContent(ContentState.createFromText(text))

test('keeps the offsets unchanged for a warning inside a single block', () => {
  const editorState = editorStateFromText('Hello world')

  const result = updateLexiqaWarnings(editorState, [{start: 0, end: 5}])

  expect(result).toHaveLength(1)
  expect(result[0].start).toBe(0)
  expect(result[0].end).toBe(5)
})

test('rebases the offsets relative to the block that contains the warning', () => {
  const editorState = editorStateFromText('Hello world\nGoodbye now')

  const result = updateLexiqaWarnings(editorState, [{start: 12, end: 19}])

  expect(result).toHaveLength(1)
  expect(result[0].start).toBe(0)
  expect(result[0].end).toBe(7)
})

test('drops warnings that fall outside every block boundary', () => {
  const editorState = editorStateFromText('Hello world')

  const result = updateLexiqaWarnings(editorState, [{start: 100, end: 105}])

  expect(result).toEqual([])
})

test('adjusts a warning that starts on the newline joining two blocks', () => {
  const editorState = editorStateFromText('ab\ncd')

  const result = updateLexiqaWarnings(editorState, [{start: 2, end: 3}])

  expect(result).toHaveLength(1)
  expect(result[0].start).toBe(0)
  expect(result[0].end).toBe(0)
})
