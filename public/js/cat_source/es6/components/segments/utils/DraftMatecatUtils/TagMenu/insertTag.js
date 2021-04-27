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

export default insertTag
