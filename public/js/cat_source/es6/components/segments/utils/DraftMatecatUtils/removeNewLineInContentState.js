import {
    Modifier,
    SelectionState,
} from 'draft-js';

/**
 *
 * @param editorState - the current EditorState
 * @returns {{contentState: *, newLineMap: []}} - the new ContentState in which every _0A or _0D  tag is replaced with ''
 * and an array with all tags removed mapped as {blockKey, selectionState}
 */
const removeNewLineInContentState = (editorState) => {
    let contentState = editorState.getCurrentContent();
    let newLineMap = [];
    let blocks = contentState.getBlockMap();
    // these regex match only the first occurence in string
    const newLineRegex = /##\$(_0A)\$##/;
    const carriageReturnRegex = /##\$(_0D)\$##/;
    // start replacing
    blocks.forEach( contentBlock => {
        // get block key
        const blockKey = contentBlock.getKey();
        // get block plain text
        let blockText = contentBlock.getText();
        let matchArray, selectionState;
        // find first tag inside block
        while ((matchArray = newLineRegex.exec(blockText)) !== null || (matchArray = carriageReturnRegex.exec(blockText)) !== null) {
            // set selection on tag
            selectionState = new SelectionState({
                anchorKey: blockKey,
                anchorOffset: matchArray.index,
                focusKey: blockKey,
                focusOffset: matchArray[0].length + matchArray.index
            });
            // update blockText removing tag found
            if(matchArray[1] === '_0A'){
                blockText = blockText.replace(newLineRegex, '');
            }else{
                blockText = blockText.replace(carriageReturnRegex, '');
            }
            // remove encoded Tag from text for next scan
            contentState = Modifier.removeRange(
                contentState,
                selectionState,
                'forward',
            );
            // save blockRelative tag selection, where anchorOffset == focusOffset (collapsed)
            selectionState = contentState.getSelectionAfter();
            newLineMap.push({selectionState, blockKey})
        }
    });
    return {contentState, newLineMap};
};

export default removeNewLineInContentState;
