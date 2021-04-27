const getEntitiesInFragment = (fragment, editorState) => {
  const contentState = editorState.getCurrentContent()
  const entities = {}
  try {
    fragment.forEach((block) => {
      block.getCharacterList().forEach((character) => {
        if (character.entity) {
          entities[character.entity] = contentState.getEntity(character.entity)
        }
      })
    })
  } catch (e) {
    if (e instanceof TypeError) {
      console.log('Invalid fragment')
    }
  }
  return entities
}

export default getEntitiesInFragment
