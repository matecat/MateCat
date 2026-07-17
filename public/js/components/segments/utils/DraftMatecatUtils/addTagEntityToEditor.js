import {EditorState} from 'draft-js'
import {getEditorStateWithTag} from './TagMenu/insertTag'

const addTagEntityToEditor = (editorState, tag, selectionState = null) => {
  if (!selectionState) {
    let newEditorState = EditorState.moveSelectionToEnd(editorState)
    selectionState = newEditorState.getSelection()
  }

  return getEditorStateWithTag({tag, editorState, selectionState})
}

export default addTagEntityToEditor
