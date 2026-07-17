import {EditorState} from 'draft-js'

/**
 *
 * @param editorState - current editorState
 * @param entityBlueprint - the JS object that represent a Tag (TagStruct)
 * @returns {{entityKey: *, editorState: *}}
 */
const entityBuilder = (editorState, entityBlueprint) => {
  let contentState = editorState.getCurrentContent()
  contentState = contentState.createEntity(
    entityBlueprint.type,
    entityBlueprint.mutability,
    entityBlueprint.data,
  )

  const entityKey = contentState.getLastCreatedEntityKey()

  const editorStateWithEntity = EditorState.push(
    editorState,
    contentState,
    'apply-entity',
  )

  return {
    editorState: editorStateWithEntity,
    entityKey,
  }
}

export default entityBuilder
