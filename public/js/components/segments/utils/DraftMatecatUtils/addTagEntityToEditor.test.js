import {EditorState, ContentState, SelectionState} from 'draft-js'
import addTagEntityToEditor from './addTagEntityToEditor'

describe('addTagEntityToEditor', () => {
  test('inserts the tag at the end of the content when no selectionState is given', () => {
    const editorState = EditorState.createWithContent(
      ContentState.createFromText('hello'),
    )
    const tag = {type: 'TAG', mutability: 'IMMUTABLE', data: {placeholder: 'X'}}

    const updated = addTagEntityToEditor(editorState, tag)

    expect(updated.getCurrentContent().getPlainText()).toBe('hello​X​')
  })

  test('inserts the tag at an explicit selectionState when provided', () => {
    const editorState = EditorState.createWithContent(
      ContentState.createFromText('hello'),
    )
    const blockKey = editorState.getCurrentContent().getFirstBlock().getKey()
    const selectionState = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 0,
      focusOffset: 0,
    })
    const tag = {type: 'TAG', mutability: 'IMMUTABLE', data: {placeholder: 'Y'}}

    const updated = addTagEntityToEditor(editorState, tag, selectionState)

    expect(updated.getCurrentContent().getPlainText()).toBe('​Y​hello')
  })
})
