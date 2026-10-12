import {EditorState, ContentState, SelectionState} from 'draft-js'
import replaceMultipleText from './replaceMultipleText'

test('replaces the given offset range with the provided value', () => {
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('hello world'),
  )

  const result = replaceMultipleText(editorState, [
    {start: 0, end: 5, value: 'HI'},
  ])

  expect(result.getCurrentContent().getPlainText()).toBe('HI world')
})

test('applies each replacement sequentially on the updated content', () => {
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('hello world'),
  )

  const result = replaceMultipleText(editorState, [
    {start: 0, end: 5, value: 'HI'},
    {start: 0, end: 2, value: 'YO'},
  ])

  expect(result.getCurrentContent().getPlainText()).toBe('YO world')
})

test('replaces inside the block the selection starts in, leaving the others alone', () => {
  // A target ending with a newline tag has more than one block: the offsets
  // belong to the block the selection starts in, and every other block must be
  // left untouched. Replacing in each block instead appended the new text to
  // the last one and left the selected text unchanged.
  const contentState = ContentState.createFromText('aaaa\nbbbb')
  const editorState = EditorState.createWithContent(contentState)

  const result = replaceMultipleText(editorState, [
    {start: 0, end: 2, value: 'X'},
  ])

  const blocks = result.getCurrentContent().getBlocksAsArray()
  expect(blocks[0].getText()).toBe('Xaa')
  expect(blocks[1].getText()).toBe('bbbb')
})

test('replaces inside the block the selection starts in when it is not the first', () => {
  const contentState = ContentState.createFromText('aaaa\nbbbb')
  const secondBlockKey = contentState.getBlocksAsArray()[1].getKey()
  const editorState = EditorState.acceptSelection(
    EditorState.createWithContent(contentState),
    SelectionState.createEmpty(secondBlockKey).merge({
      anchorOffset: 1,
      focusOffset: 3,
    }),
  )

  const result = replaceMultipleText(editorState, [
    {start: 1, end: 3, value: 'ZZ'},
  ])

  const blocks = result.getCurrentContent().getBlocksAsArray()
  expect(blocks[0].getText()).toBe('aaaa')
  expect(blocks[1].getText()).toBe('bZZb')
})

test('returns the same editor state when there is nothing to replace', () => {
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('hello world'),
  )

  const result = replaceMultipleText(editorState, [])

  expect(result).toBe(editorState)
})
