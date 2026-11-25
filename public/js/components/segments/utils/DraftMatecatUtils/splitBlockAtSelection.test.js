import {EditorState, ContentState, SelectionState} from 'draft-js'
import splitBlockAtSelection from './splitBlockAtSelection'

test('splits the current block at the editor selection', () => {
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('hello world'),
  )
  const selection = editorState.getSelection().merge({
    anchorOffset: 5,
    focusOffset: 5,
  })
  const withSelection = EditorState.forceSelection(editorState, selection)

  const result = splitBlockAtSelection(withSelection)

  const blocks = result.getCurrentContent().getBlocksAsArray()
  expect(blocks).toHaveLength(2)
  expect(blocks[0].getText()).toBe('hello')
  expect(blocks[1].getText()).toBe(' world')
})

test('splits at an explicitly provided selection instead of the current one', () => {
  const editorState = EditorState.createWithContent(
    ContentState.createFromText('hello world'),
  )
  const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()
  const customSelection = SelectionState.createEmpty(blockKey).merge({
    anchorOffset: 3,
    focusOffset: 3,
  })

  const result = splitBlockAtSelection(editorState, customSelection)

  const blocks = result.getCurrentContent().getBlocksAsArray()
  expect(blocks).toHaveLength(2)
  expect(blocks[0].getText()).toBe('hel')
  expect(blocks[1].getText()).toBe('lo world')
})
