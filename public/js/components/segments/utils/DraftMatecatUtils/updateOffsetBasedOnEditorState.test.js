import {EditorState, ContentState} from 'draft-js'
import updateOffsetBasedOnEditorState from './updateOffsetBasedOnEditorState'

const editorStateFromText = (text) =>
  EditorState.createWithContent(ContentState.createFromText(text))

test('keeps the offsets unchanged for a warning inside a single block', () => {
  const editorState = editorStateFromText('Hello world')

  const result = updateOffsetBasedOnEditorState(editorState, [
    {start: 0, end: 5},
  ])

  expect(result).toHaveLength(1)
  expect(result[0].start).toBe(0)
  expect(result[0].end).toBe(5)
  expect(result[0].blockKey).toBe(
    editorState.getCurrentContent().getFirstBlock().getKey(),
  )
})

test('rebases the offsets relative to the block that contains the warning', () => {
  const editorState = editorStateFromText('Hello world\nGoodbye now')

  const result = updateOffsetBasedOnEditorState(editorState, [
    {start: 12, end: 19},
  ])

  const secondBlockKey = editorState
    .getCurrentContent()
    .getBlockAfter(editorState.getCurrentContent().getFirstBlock().getKey())
    .getKey()

  expect(result).toHaveLength(1)
  expect(result[0].start).toBe(0)
  expect(result[0].end).toBe(7)
  expect(result[0].blockKey).toBe(secondBlockKey)
})

test('drops warnings that fall outside every block boundary', () => {
  const editorState = editorStateFromText('Hello world')

  const result = updateOffsetBasedOnEditorState(editorState, [
    {start: 100, end: 105},
  ])

  expect(result).toEqual([])
})

test('adjusts a warning that starts on the newline joining two blocks', () => {
  const editorState = editorStateFromText('ab\ncd')

  const result = updateOffsetBasedOnEditorState(editorState, [
    {start: 2, end: 3},
  ])

  expect(result).toHaveLength(1)
  expect(result[0].start).toBe(0)
  expect(result[0].end).toBe(0)
})

test('processes every warning in the provided list', () => {
  const editorState = editorStateFromText('Hello world\nGoodbye now')

  const result = updateOffsetBasedOnEditorState(editorState, [
    {start: 0, end: 5},
    {start: 12, end: 19},
  ])

  expect(result).toHaveLength(2)
})
