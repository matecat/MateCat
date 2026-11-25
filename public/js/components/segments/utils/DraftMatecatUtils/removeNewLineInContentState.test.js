import {EditorState, ContentState} from 'draft-js'
import removeNewLineInContentState from './removeNewLineInContentState'

const editorStateFromText = (text) =>
  EditorState.createWithContent(ContentState.createFromText(text))

test('removes a line-feed placeholder and records its selection', () => {
  const editorState = editorStateFromText('foo##$_0A$##bar')

  const {contentState, newLineMap} = removeNewLineInContentState(editorState)

  expect(contentState.getPlainText()).toBe('foobar')
  expect(newLineMap).toHaveLength(1)
  expect(newLineMap[0].blockKey).toBe(contentState.getFirstBlock().getKey())
})

test('removes a carriage-return placeholder', () => {
  const editorState = editorStateFromText('foo##$_0D$##bar')

  const {contentState, newLineMap} = removeNewLineInContentState(editorState)

  expect(contentState.getPlainText()).toBe('foobar')
  expect(newLineMap).toHaveLength(1)
})

test('treats a combined CRLF placeholder as a single unit', () => {
  const editorState = editorStateFromText('foo##$_0D$####$_0A$##bar')

  const {contentState, newLineMap} = removeNewLineInContentState(editorState)

  expect(contentState.getPlainText()).toBe('foobar')
  expect(newLineMap).toHaveLength(1)
})

test('removes multiple placeholders from the same block in order', () => {
  const editorState = editorStateFromText('a##$_0A$##b##$_0A$##c')

  const {contentState, newLineMap} = removeNewLineInContentState(editorState)

  expect(contentState.getPlainText()).toBe('abc')
  expect(newLineMap).toHaveLength(2)
})

test('returns an empty map when there is nothing to remove', () => {
  const editorState = editorStateFromText('plain text')

  const {contentState, newLineMap} = removeNewLineInContentState(editorState)

  expect(contentState.getPlainText()).toBe('plain text')
  expect(newLineMap).toEqual([])
})
