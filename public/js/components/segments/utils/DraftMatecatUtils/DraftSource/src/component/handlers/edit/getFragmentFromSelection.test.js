import {EditorState, ContentState, SelectionState} from 'draft-js'
import getFragmentFromSelection from './getFragmentFromSelection'

describe('getFragmentFromSelection', () => {
  test('returns null for a collapsed selection', () => {
    const editorState = EditorState.createWithContent(
      ContentState.createFromText('hello world'),
    )
    expect(getFragmentFromSelection(editorState)).toBeNull()
  })

  test('returns the content-state fragment for a non-collapsed selection', () => {
    let editorState = EditorState.createWithContent(
      ContentState.createFromText('hello world'),
    )
    const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 0,
      focusOffset: 5,
    })
    editorState = EditorState.forceSelection(editorState, selection)

    const fragment = getFragmentFromSelection(editorState)

    expect(fragment).not.toBeNull()
    expect(fragment.first().getText()).toBe('hello')
  })
})
