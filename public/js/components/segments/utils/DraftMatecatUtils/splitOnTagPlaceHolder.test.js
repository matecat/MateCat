import {EditorState, ContentState, SelectionState} from 'draft-js'
import splitOnTagPlaceholder from './splitOnTagPlaceHolder'
import removeNewLineInContentState from './removeNewLineInContentState'

test('returns the content state unchanged when there is no newLineMap', () => {
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('plain text'),
  )

  const result = splitOnTagPlaceholder(editorState, null)

  expect(result).toBe(editorState.getCurrentContent())
})

test('splits a single block at each recorded split point, in order', () => {
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('foobarbaz'),
  )
  const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()

  const newLineMap = [
    {
      blockKey,
      selectionState: SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 3,
        focusOffset: 3,
      }),
    },
    {
      blockKey,
      selectionState: SelectionState.createEmpty(blockKey).merge({
        anchorOffset: 6,
        focusOffset: 6,
      }),
    },
  ]

  const result = splitOnTagPlaceholder(editorState, newLineMap)

  const blocks = result.getBlocksAsArray()
  expect(blocks.map((block) => block.getText())).toEqual(['foo', 'bar', 'baz'])
})

test('round-trips with removeNewLineInContentState across multiple line breaks', () => {
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('foo##$_0A$##bar##$_0A$##baz'),
  )

  const {contentState, newLineMap} = removeNewLineInContentState(editorState)
  const editorStateWithoutTags = EditorState.createWithContent(contentState)

  const result = splitOnTagPlaceholder(editorStateWithoutTags, newLineMap)

  const blocks = result.getBlocksAsArray()
  expect(blocks.map((block) => block.getText())).toEqual(['foo', 'bar', 'baz'])
})
