import {Modifier, EditorState} from 'draft-js'
import entityBuilder from './entityBuilder'

/**
 *
 * @param editorState - current editorState
 * @param entityBlueprint - the JS object that represent a Tag (TagStruct)
 * @param selectionState - the selection used to insert the tag, alternatively current selection will be used
 * @returns editorStateUpdated
 */
const insertEntityAtSelection = (
  editorState,
  entityBlueprint,
  selectionState = null,
) => {
  const selection = selectionState ? selectionState : editorState.getSelection()
  // Create entity
  const {editorState: editorStateWithEntity, entityKey} = entityBuilder(
    editorState,
    entityBlueprint,
  )
  const contentState = editorStateWithEntity.getCurrentContent()
  // Insert text
  const {placeholder} = entityBlueprint.data
  const contentStateWithChars = selection.isCollapsed()
    ? Modifier.insertText(contentState, selection, placeholder)
    : Modifier.replaceText(contentState, selection, placeholder)
  // Select all inserted/replaced text
  let newSelection = selection.merge({
    anchorOffset: selection.getStartOffset(),
    focusOffset: selection.getStartOffset() + placeholder.length,
  })

  // Apply entity to inserted/replaced text
  const contentStateWithEntity = Modifier.applyEntity(
    contentStateWithChars,
    newSelection,
    entityKey,
  )

  // Push new editor state
  let editorStateUpdated = EditorState.push(
    editorStateWithEntity,
    contentStateWithEntity,
    'apply-entity',
  )

  // Set selection after applied entity
  newSelection = selection.merge({
    anchorOffset: newSelection.getEndOffset(),
    focusOffset: newSelection.getEndOffset(),
  })
  editorStateUpdated = EditorState.forceSelection(
    editorStateUpdated,
    newSelection,
  )

  return editorStateUpdated
}

export default insertEntityAtSelection
