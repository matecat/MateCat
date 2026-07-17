import getEntities from './getEntities'
import {EditorState, Modifier, SelectionState, ContentState} from 'draft-js'
/**
 *
 * @param editorState
 * @returns {EditorState} editorState - A a new EditorState in which entities are displayed as placeholder
 */
const beautifyEntities = (editorState) => {
  const inlineStyle = editorState.getCurrentInlineStyle()
  const entities = getEntities(editorState) //start - end
  const entityKeys = entities.map((entity) => entity.entityKey)

  let contentState = editorState.getCurrentContent()
  let editorStateClone = editorState

  entityKeys.forEach((key) => {
    // Update entities and blocks cause previous cycle updated offsets
    // LAZY NOTE: entity.start and entity.end are block-based
    let entitiesInEditor = getEntities(editorStateClone)
    // Filter only looped tag and get data
    // Todo: add check on tag array length
    const tagEntity = entitiesInEditor.filter(
      (entity) => entity.entityKey === key,
    )[0]
    const {placeholder} = tagEntity.entity.data
    // Get block-based selection
    const selectionState = new SelectionState({
      anchorKey: tagEntity.blockKey,
      anchorOffset: tagEntity.start,
      focusKey: tagEntity.blockKey,
      focusOffset: tagEntity.end,
    })
    // Replace text of entity with placeholder
    contentState = Modifier.replaceText(
      contentState,
      selectionState,
      placeholder,
      inlineStyle,
      tagEntity.entityKey,
    )
    // Update contentState
    editorStateClone = EditorState.set(editorStateClone, {
      currentContent: contentState,
    })
  })
  return editorStateClone
}

export default beautifyEntities
