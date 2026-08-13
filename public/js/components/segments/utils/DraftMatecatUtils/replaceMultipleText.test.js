import {EditorState, ContentState} from 'draft-js'
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

test('builds a same-offset selection for every block but only commits the last one', () => {
  // Each block gets a same-offset selection, but the function replaces text
  // against the original (non-accumulated) content state on every iteration,
  // so only the last processed block's replacement survives in the result.
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('aaaa\nbbbb'),
  )

  const result = replaceMultipleText(editorState, [
    {start: 0, end: 2, value: 'X'},
  ])

  const blocks = result.getCurrentContent().getBlocksAsArray()
  expect(blocks[0].getText()).toBe('aaaa')
  expect(blocks[1].getText()).toBe('Xbb')
})

test('returns the same editor state when there is nothing to replace', () => {
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('hello world'),
  )

  const result = replaceMultipleText(editorState, [])

  expect(result).toBe(editorState)
})
