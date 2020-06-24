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
 * @param excludedTagsType - array of tags type. Entity won't be created for these tags.
 * @returns {ContentState}  contentState - A ContentState with each tag as an entity
 */
const createNewEntitiesFromMap = (editorState, excludedTagsType,  plainText = '') => {
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
                tag.offset >= (maxCharsInBlocks - contentBlock.getLength()) &&
                !excludedTagsType.includes(tag.type)
            ) {
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
                // pply entity on the previous selection
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
