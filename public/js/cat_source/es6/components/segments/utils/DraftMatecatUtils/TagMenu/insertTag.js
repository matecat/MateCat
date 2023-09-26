import {EditorState, Modifier, SelectionState} from 'draft-js'

const insertTag = (tagSuggestion, editorState, triggerText = null) => {
  let contentState = editorState.getCurrentContent()
  const originalSelection = editorState.getSelection()

  // Replace char that triggered the fn
  let selectionState = triggerText
    ? originalSelection.merge({
        anchorOffset: originalSelection.anchorOffset - triggerText.length,
        focusOffset: originalSelection.anchorOffset,
      })
    : originalSelection

  const {type, mutability, data} = tagSuggestion

  // Create a new entity
  contentState = contentState.createEntity(type, mutability, data)

  const entityKey = contentState.getLastCreatedEntityKey()
  const inlinestyle = editorState.getCurrentInlineStyle()

  // Insert entity
  let replacedContent = Modifier.replaceText(
    contentState,
    selectionState,
    data.placeholder,
    inlinestyle,
    entityKey,
  )

  return EditorState.push(editorState, replacedContent, 'insert-characters')
}

export default insertTag
