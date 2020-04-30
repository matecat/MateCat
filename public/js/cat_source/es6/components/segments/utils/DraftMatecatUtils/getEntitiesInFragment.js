const getEntitiesInFragment = (fragment, editorState) => {
    const contentState = editorState.getCurrentContent();
    const entities = {};
    fragment.forEach(block => {
        block.getCharacterList().forEach(character => {
            if (character.entity) {
                entities[character.entity] = contentState.getEntity(character.entity)
            }
        });
    });
    return entities;
};


export default getEntitiesInFragment;
