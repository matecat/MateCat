import createNewEntitiesFromMap from "./createNewEntitiesFromMap";
import linkEntities from "./linkEntities";
import beautifyEntities from "./beautifyEntities";
import {EditorState, Modifier} from 'draft-js';
import splitOnTagPlaceholder from "./splitOnTagPlaceHolder";
import removeNewLineInContentState from "./removeNewLineInContentState";
import {getSplitBlockTag} from "./tagModel";
import replaceOccurrences from "./replaceOccurrences";

/**
 *
 * @param originalEditorState
 * @param plainText - text to analyze when editor is empty
 * @returns {*|EditorState} editorStateModified - An EditorState with all known tags treated as entities
 */
const encodeContent = (originalEditorState, plainText = '') => {
    // get tag's types on which every block will be splitted
    const excludedTags = getSplitBlockTag();
    // Create entities
    const entitiesFromMap = createNewEntitiesFromMap(originalEditorState, excludedTags, plainText);
    let {contentState, tagRange} = entitiesFromMap;

    // Apply entities to EditorState
    let editorState = EditorState.push(originalEditorState, contentState, 'apply-entity');

    // NOTE: if you deactivate 'removeNewLineInContentState' and 'splitOnTagPlaceholder', remember to pass an empty
    // array as excludedTags to 'createNewEntitiesFromMap'. So every \n and \r will be showed as self-closed tags.

    // Remove LF or CR
    const {contentState: contentStateWithoutNewLines, newLineMap} = removeNewLineInContentState(editorState);
    editorState = EditorState.push(editorState, contentStateWithoutNewLines, 'remove-range');

    // Split blocks on LF or CR
    const contentSplitted = splitOnTagPlaceholder(editorState, newLineMap);
    editorState = EditorState.push(editorState, contentSplitted, 'split-block');

    // Link each openTag with its closure using entity key, otherwise tag are linked with openTagId/closeTagId
    contentState = linkEntities(editorState);
    editorState = EditorState.push(originalEditorState, contentState, 'change-block-data');

    // Replace each tag text with a placeholder
    // contentState = beautifyEntities(editorState);
    editorState = beautifyEntities(editorState);
    //editorState = EditorState.push(editorState, contentState, 'insert-characters');

    // Unescape residual html entities after tag identification
    editorState = replaceOccurrences(editorState, '&lt;', '<');
    editorState = replaceOccurrences(editorState, '&gt;', '>');

    const decorator = originalEditorState.getDecorator();
    // We use ContentState to create a new Editor without history
    contentState = editorState.getCurrentContent();
    editorState = EditorState.createWithContent(contentState, decorator);

    return {editorState, tagRange};
};

export default encodeContent;
