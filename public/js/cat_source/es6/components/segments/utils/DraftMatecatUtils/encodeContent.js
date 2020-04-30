import createNewEntitiesFromMap from "./createNewEntitiesFromMap";
import linkEntities from "./linkEntities";
import beautifyEntities from "./beautifyEntities";
import {EditorState} from 'draft-js';

/**
 *
 * @param editorState
 * @param plainText - text to analyze when editor is empty
 * @returns {*|EditorState} editorStateModified - An EditorState with all known tags treated as entities
 */
const encodeContent = (editorState, plainText = '') => {

    // Create entities
    let newContent = createNewEntitiesFromMap(editorState, plainText);
    // Apply entities to EditorState
    let editorStateModified = EditorState.push(editorState, newContent, 'apply-entity');
    // Link each openTag with its closure using entity key, otherwise tag are linked with openTagId/closeTagId
    newContent = linkEntities(editorStateModified);
    editorStateModified = EditorState.push(editorState, newContent, 'change-block-data');
    // Replace each tag text with a placeholder
    newContent = beautifyEntities(editorStateModified);
    editorStateModified = EditorState.push(editorState, newContent, 'change-block-data');
    return editorStateModified;
};

export default encodeContent;
