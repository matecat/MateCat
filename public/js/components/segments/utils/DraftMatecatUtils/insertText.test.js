import {EditorState, ContentState, SelectionState} from 'draft-js'
import insertText from './insertText'

describe('insertText', () => {
  test('inserts text at a collapsed selection', () => {
    const editorState = EditorState.createEmpty()
    const updated = insertText(editorState, 'hello')
    expect(updated.getCurrentContent().getPlainText()).toBe('hello')
  })

  test('replaces text for a non-collapsed selection', () => {
    let editorState = EditorState.createWithContent(
      ContentState.createFromText('hello world'),
    )
    const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 0,
      focusOffset: 5,
    })
    editorState = EditorState.forceSelection(editorState, selection)

    const updated = insertText(editorState, 'bye')
    expect(updated.getCurrentContent().getPlainText()).toBe('bye world')
  })
})
