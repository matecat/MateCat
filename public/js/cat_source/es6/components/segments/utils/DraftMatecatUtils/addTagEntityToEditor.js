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

  // Insert ZWSP char before entity
  let replacedContent = Modifier.replaceText(
    contentState,
    selectionState,
    '​',
    inlinestyle,
    null,
  )

  // move selection forward by one char
  selectionState = selectionState.merge({
    anchorOffset: selectionState.anchorOffset + 1,
    focusOffset: selectionState.anchorOffset + 1,
  })

  // Insert entity
  replacedContent = Modifier.insertText(
    replacedContent,
    selectionState,
    data.placeholder,
    inlinestyle,
    entityKey,
  )

  // Move selection after entity
  selectionState = selectionState.merge({
    anchorOffset: selectionState.anchorOffset + data.placeholder.length,
    focusOffset: selectionState.anchorOffset + data.placeholder.length,
  })

  // Insert ZWSP after entity
  replacedContent = Modifier.insertText(
    replacedContent,
    selectionState,
    '​',
    inlinestyle,
    null,
  )

  return EditorState.push(editorState, replacedContent, 'insert-characters')
}

export default addTagEntityToEditor
