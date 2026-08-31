import {EditorState, ContentState} from 'draft-js'
import replaceOccurrences from './replaceOccurrences'

const editorStateFromText = (text) =>
  EditorState.createWithContent(ContentState.createFromText(text))

test('replaces every occurrence when no index is given', () => {
  const editorState = editorStateFromText('foo bar foo')

  const result = replaceOccurrences(editorState, 'foo', 'baz')

  expect(result.getCurrentContent().getPlainText()).toBe('baz bar baz')
})

test('replaces matches case-insensitively', () => {
  const editorState = editorStateFromText('Foo bar FOO')

  const result = replaceOccurrences(editorState, 'foo', 'baz')

  expect(result.getCurrentContent().getPlainText()).toBe('baz bar baz')
})

test('replaces only the occurrence at the given index', () => {
  const editorState = editorStateFromText('foo bar foo')

  const result = replaceOccurrences(editorState, 'foo', 'baz', 1)

  expect(result.getCurrentContent().getPlainText()).toBe('foo bar baz')
})

test('escapes regex special characters in the search term', () => {
  const editorState = editorStateFromText('a.b c.d a.b')

  const result = replaceOccurrences(editorState, 'a.b', 'X')

  expect(result.getCurrentContent().getPlainText()).toBe('X c.d X')
})

test('leaves the text unchanged when there is no match', () => {
  const editorState = editorStateFromText('hello world')

  const result = replaceOccurrences(editorState, 'missing', 'X')

  expect(result.getCurrentContent().getPlainText()).toBe('hello world')
})
