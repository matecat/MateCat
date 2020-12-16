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
 * @returns {{ContentState, tagRange}} contentState - The object with the ContentState with each tag mapped as an entity
 * and the array of the mapped tags.
 */
const createNewEntitiesFromMap = (editorState, excludedTagsType,  plainText = '') => {
    let contentState = editorState.getCurrentContent();
    // If editor content is empty, create new content from plainText
    if(!contentState.hasText() || plainText !== ''){
        contentState = ContentState.createFromText(plainText);
    }
    // Compute tag range ( all tags are included, also nbsp, tab, CR and LF)
    const tagRange = matchTag(contentState.getPlainText());
    // Apply each entity to the block where it belongs
    const blocks = contentState.getBlockMap();
    let maxCharsInBlocks = 0;
    blocks.forEach((contentBlock) => {
        maxCharsInBlocks += contentBlock.getLength();
        let lengthDiff = 0;
        tagRange.sort((a, b) => {return a.offset-b.offset});

        tagRange.forEach( tag =>{

            if (tag.offset < maxCharsInBlocks &&
                (tag.offset + tag.length) <= maxCharsInBlocks &&
                tag.offset >= (maxCharsInBlocks - contentBlock.getLength()) &&
                !excludedTagsType.includes(tag.data.name)
            ) {
                // Clone tag
                const tagEntity = {...tag};
                // Each block start with offset = 0 so we have to adapt selection
                let selectionState = SelectionState.createEmpty(contentBlock.getKey())
                selectionState = selectionState.merge({
                    anchorOffset: (tag.offset - (maxCharsInBlocks - contentBlock.getLength())) - lengthDiff,
                    focusOffset: ((tag.offset + tag.length) - (maxCharsInBlocks - contentBlock.getLength())) - lengthDiff
                });
                // Create entity
                const {type, mutability, data} = tagEntity;
                const contentStateWithEntity = contentState.createEntity(type, mutability, data);
                const entityKey = contentStateWithEntity.getLastCreatedEntityKey();

                const inlineStyle = contentBlock.getInlineStyleAt(tag.offset);
                //const inlineStyle = editorState.getCurrentInlineStyle();

                // apply entity on the previous selection
                /*contentState = Modifier.applyEntity(
                    contentState,
                    selectionState,
                    entityKey
                );*/
                // Beautify
                contentState = Modifier.replaceText(
                    contentState,
                    selectionState,
                    data.placeholder,
                    inlineStyle,
                    entityKey
                );

                lengthDiff +=  (selectionState.focusOffset - selectionState.anchorOffset) - data.placeholder.length;
            }
        });
    });
    return {contentState, tagRange}
};


export default createNewEntitiesFromMap;
