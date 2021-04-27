import {Modifier, EditorState} from 'draft-js'

const addTagEntityToEditor = (editorState, tag, selectionState = null) => {
  let contentState = editorState.getCurrentContent()
  //
  if (!selectionState) {
    let newEditorState = EditorState.moveSelectionToEnd(editorState)
    selectionState = newEditorState.getSelection()
  }

  const {type, mutability, data} = tag
  // Creo la nuova entità
  contentState = contentState.createEntity(type, mutability, data)

  const entityKey = contentState.getLastCreatedEntityKey()
  const inlinestyle = editorState.getCurrentInlineStyle()

  // Sostituisce il contenuto
  const replacedContent = Modifier.replaceText(
    contentState,
    selectionState,
    data.placeholder,
    inlinestyle,
    entityKey,
  )

  return EditorState.push(editorState, replacedContent, 'apply-entity')
}

export default addTagEntityToEditor
