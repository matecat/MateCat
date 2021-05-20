import {Modifier, EditorState} from 'draft-js'

const splitBlockAtSelection = (editorState, selection = null) => {
  const contentState = editorState.getCurrentContent()
  const selectionState = selection ? selection : editorState.getSelection()
  const newContentState = Modifier.splitBlock(contentState, selectionState)
  return EditorState.push(editorState, newContentState, 'split-block')
}

export default splitBlockAtSelection
