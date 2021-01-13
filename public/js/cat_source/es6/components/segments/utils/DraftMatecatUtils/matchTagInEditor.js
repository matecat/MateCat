import getEntities from "./getEntities";
import tagFromEntity from "./tagFromEntity";

/**
 *
 * @param editorState
 * @returns {[]|*} - Array of tag placed in current editor state
 */
const matchTagInEditor = (editorState) => {

    let contentState = editorState.getCurrentContent();
    if(!contentState.hasText()) return [];

    const entities = getEntities(editorState);
    let tagRange = [];

    entities.forEach(entity => {
        tagRange.push(tagFromEntity(entity))
    });

    return tagRange;
};

export default matchTagInEditor;
