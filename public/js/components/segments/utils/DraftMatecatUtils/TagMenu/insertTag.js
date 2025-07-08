import {EditorState, Modifier} from 'draft-js'

const insertTag = (tagSuggestion, editorState, triggerText = null) => {
  const originalSelection = editorState.getSelection()

  // Replace char that triggered the fn
  const selectionState = triggerText
    ? originalSelection.merge({
        anchorOffset: originalSelection.anchorOffset - triggerText.length,
        focusOffset: originalSelection.anchorOffset,
      })
    : originalSelection

  return getEditorStateWithTag({
    tag: tagSuggestion,
    editorState,
    selectionState,
  })
}

export const getEditorStateWithTag = ({tag, editorState, selectionState}) => {
  let contentState = editorState.getCurrentContent()
  let selectionStateUpdated = selectionState

  const {type, mutability, data} = tag

  // Create a new entity
  contentState = contentState.createEntity(type, mutability, data)

  const entityKey = contentState.getLastCreatedEntityKey()
  const inlinestyle = editorState.getCurrentInlineStyle()

  // Insert ZWSP char before entity
  let replacedContent = Modifier.replaceText(
    contentState,
    selectionStateUpdated,
    '​',
    inlinestyle,
    null,
  )

  // move selection forward by one char
  selectionStateUpdated = selectionState.merge({
    anchorOffset: selectionState.anchorOffset + 1,
    focusOffset: selectionState.anchorOffset + 1,
  })

  // Insert entity
  replacedContent = Modifier.insertText(
    replacedContent,
    selectionStateUpdated,
    data.placeholder,
    inlinestyle,
    entityKey,
  )

  // Move selection after entity
  selectionStateUpdated = selectionState.merge({
    anchorOffset: selectionStateUpdated.anchorOffset + data.placeholder.length,
    focusOffset: selectionStateUpdated.anchorOffset + data.placeholder.length,
  })

  // Insert ZWSP after entity
  replacedContent = Modifier.insertText(
    replacedContent,
    selectionStateUpdated,
    '​',
    inlinestyle,
    null,
  )

  return EditorState.push(editorState, replacedContent, 'insert-characters')
}

export default insertTag
