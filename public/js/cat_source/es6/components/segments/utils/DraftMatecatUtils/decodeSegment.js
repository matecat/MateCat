import getEntities from "./getEntities";
import {
    EditorState,
    Modifier,
    SelectionState
} from 'draft-js';
/**
 *
 * @param editorState
 * @returns {}
 */
const decodeSegment  = (editorState) => {


    let contentState = editorState.getCurrentContent();
    if(!contentState.hasText()) return {entities: [], decodedSegment: contentState.getPlainText()}

    const entities = getEntities(editorState).sort((a, b) => a.start-b.start); //start - end

    // Adapt offset from block to absolute
    const blocks = contentState.getBlockMap();
    let plainEditorText = contentState.getPlainText();
    let totalBlocksLength = 0;
    let slicedLength = 0;
    blocks.forEach( block => {
        entities.forEach( tagEntity => {
            const {encodedText} = tagEntity.entity.data;
            // add previous block length and previous replace length diff
            const start = tagEntity.start + totalBlocksLength - slicedLength;
            const end  = tagEntity.end + totalBlocksLength - slicedLength;
            plainEditorText = plainEditorText.slice(0,start) + encodedText + plainEditorText.slice(end);
            slicedLength +=  (end - start) - encodedText.length;
        })
        // Block lenght plus newline char
        totalBlocksLength += block.getLength() + 1
    })

    const decodedSegmentPlain = plainEditorText.replace(/\n/gi, config.lfPlaceholder);

    return { entitiesRange: entities, decodedSegment: decodedSegmentPlain }
};

export default decodeSegment;
