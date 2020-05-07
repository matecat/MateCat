import matchTag from "./matchTag";
import {
    Modifier,
    SelectionState,
    ContentState
} from 'draft-js';

/**
 *
 * @param editorState - current editor state, can be empty
 * @param plainText - text where each entity applies
 * @returns {ContentState}  contentState - A ContentState with each tag as an entity
 */
const createNewEntitiesFromMap = (editorState, plainText = '') => {
    let contentState = editorState.getCurrentContent();
    // If editor content is empty, create new content from plainText
    if(!contentState.hasText() || plainText !== ''){
        contentState = ContentState.createFromText(plainText);
    }
    // Compute tag range
    const tagRange = matchTag(contentState.getPlainText());
    // Apply each entity to the block where it belongs
    const blocks = contentState.getBlockMap();
    let maxCharsInBlocks = 0;
    blocks.forEach((contentBlock) => {
        maxCharsInBlocks += contentBlock.getLength();
        tagRange.forEach( tag =>{
            if (tag.offset < maxCharsInBlocks &&
                (tag.offset + tag.length) <= maxCharsInBlocks &&
                tag.offset >= (maxCharsInBlocks - contentBlock.getLength())) {
                // Clone tag
                const tagEntity = {...tag};
                // Each block start with offset = 0 so we have to adapt selection
                const selectionState = new SelectionState({
                    anchorKey: contentBlock.getKey(),
                    anchorOffset: (tag.offset - (maxCharsInBlocks - contentBlock.getLength())),
                    focusKey: contentBlock.getKey(),
                    focusOffset: ((tag.offset + tag.length) - (maxCharsInBlocks - contentBlock.getLength()))
                });
                // Create entity
                const {type, mutability, data} = tagEntity;
                const contentStateWithEntity = contentState.createEntity(type, mutability, data);
                const entityKey = contentStateWithEntity.getLastCreatedEntityKey();
                // Apply entity on the previous selection
                contentState = Modifier.applyEntity(
                    contentState,
                    selectionState,
                    entityKey
                );
            }
        });
    });

    return {contentState, tagRange}
};

export default createNewEntitiesFromMap;
