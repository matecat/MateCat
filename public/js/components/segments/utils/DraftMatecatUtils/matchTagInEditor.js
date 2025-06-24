import getEntities from './getEntities'
import tagFromEntity from './tagFromEntity'

/**
 *
 * @param editorState
 * @param entities
 * @returns {[]|*} - Array of tag placed in current editor state
 */
const matchTagInEditor = (editorState, entities = []) => {
  let contentState = editorState.getCurrentContent()
  if (!contentState.hasText()) return []

  if (
    !Array.isArray(entities) ||
    (Array.isArray(entities) && entities.length === 0)
  ) {
    entities = getEntities(editorState)
  }
  let tagRange = []
  entities.forEach((entity) => {
    tagRange.push(tagFromEntity(entity))
  })

  return tagRange
}

export default matchTagInEditor
