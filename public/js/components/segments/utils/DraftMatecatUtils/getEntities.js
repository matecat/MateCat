/**
 *
 * @param editorState
 * @param [entityType]
 * @returns {[]} An array of entities with each entity position
 */
const getEntities = (editorState, entityName = null) => {
  const content = editorState.getCurrentContent()
  const entities = []
  content.getBlocksAsArray().forEach((block) => {
    let selectedEntity = null
    block.findEntityRanges(
      (character) => {
        if (character.getEntity() !== null) {
          const entity = content.getEntity(character.getEntity())
          const entityData = entity.getData()
          if (
            !entityName ||
            (entityName && entityData.name && entityData.name === entityName)
          ) {
            selectedEntity = {
              entityKey: character.getEntity(),
              blockKey: block.getKey(),
              entity: content.getEntity(character.getEntity()),
            }
            return true
          }
        }
        return false
      },
      (start, end) => {
        entities.push({...selectedEntity, start, end})
      },
    )
  })
  // LAZY NOTE: returned entity.start and entity.end are block-based offsets
  return entities
}

export default getEntities
