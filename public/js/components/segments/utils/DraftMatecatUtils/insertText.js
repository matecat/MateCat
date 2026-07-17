import {Modifier, EditorState} from 'draft-js'

const insertText = (editorState, text) => {
  const selection = editorState.getSelection()
  const contentState = editorState.getCurrentContent()
  const charsInsert = selection.isCollapsed()
    ? Modifier.insertText(contentState, selection, text)
    : Modifier.replaceText(contentState, selection, text)
  return EditorState.push(editorState, charsInsert, 'insert-characters')
}

export default insertText
