import {EditorState, ContentState, SelectionState} from 'draft-js'
import getSelectedText from './getSelectedText'

describe('getSelectedText', () => {
  test('returns the substring covered by the current selection', () => {
    let editorState = EditorState.createWithContent(
      ContentState.createFromText('hello world'),
    )
    const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 0,
      focusOffset: 5,
    })
    editorState = EditorState.forceSelection(editorState, selection)

    expect(getSelectedText(editorState)).toBe('hello')
  })

  test('returns an empty string for a collapsed selection', () => {
    const editorState = EditorState.createWithContent(
      ContentState.createFromText('hello world'),
    )
    expect(getSelectedText(editorState)).toBe('')
  })
})
