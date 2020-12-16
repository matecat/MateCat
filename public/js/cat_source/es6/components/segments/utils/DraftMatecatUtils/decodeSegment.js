import getEntities from "./getEntities";
import {
    EditorState,
    Modifier,
    SelectionState
} from 'draft-js';
/**
 *
 * @param editorState
 * @returns {string}
 */
const decodeSegment  = (editorState) => {


    let contentState = editorState.getCurrentContent();
    // Se non c'è niente da decodare ritorna così
    if(!contentState.hasText()) return contentState.getPlainText();

    const inlineStyle = editorState.getCurrentInlineStyle();
    const entities = getEntities(editorState); //start - end
    const entityKeys =  entities.map( entity => entity.entityKey);

    let lengthDiff = 0;

    entityKeys.forEach( key => {
        // Update entities and blocks cause previous cycle updated offsets
        // LAZY NOTE: entity.start and entity.end are block-based
        //let entitiesInEditor = getEntities(editorStateClone);
        // Filter only looped tag and get data
        // Todo: add check on tag array length
        const tagEntity = entities.filter( entity => entity.entityKey === key)[0];
        const {encodedText} = tagEntity.entity.data;
        // Get block-based selection
        let selectionState = SelectionState.createEmpty(tagEntity.blockKey)
        selectionState = selectionState.merge({
            anchorOffset: tagEntity.start - lengthDiff,
            focusOffset: tagEntity.end - lengthDiff
        });
        // Replace text of entity with original text and delete entity key
        contentState = Modifier.replaceText(
            contentState,
            selectionState,
            encodedText,
            inlineStyle,
            null
        );
        // Update contentState
        //editorStateClone = EditorState.set(editorStateClone, {currentContent: contentState});

        lengthDiff +=  (selectionState.focusOffset - selectionState.anchorOffset) - encodedText.length
    });
    return contentState.getPlainText().replace(/\n/gi, config.lfPlaceholder);
};

export default decodeSegment;
