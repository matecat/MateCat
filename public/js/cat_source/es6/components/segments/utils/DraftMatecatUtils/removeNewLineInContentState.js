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
    const lineFeedRegex = /##\$(_0A)\$##/;
    const carriageReturnRegex = /##\$(_0D)\$##/;
    const mixedCRLFRegex = /##\$_0D\$####\$_0A\$##/;

    // start replacing
    blocks.forEach( contentBlock => {
        // get block key
        const blockKey = contentBlock.getKey();
        // get block plain text
        let blockText = contentBlock.getText();
        let matchArray, selectionState;

        // find first tag inside block
        // Here it is important to respect this order of evaluation: CR+LF, CR, and LF
        while ((matchArray = mixedCRLFRegex.exec(blockText)) !== null ||
        (matchArray = carriageReturnRegex.exec(blockText)) !== null ||
        (matchArray = lineFeedRegex.exec(blockText)) !== null) {
            // set selection on tag
            selectionState = new SelectionState({
                anchorKey: blockKey,
                anchorOffset: matchArray.index,
                focusKey: blockKey,
                focusOffset: matchArray[0].length + matchArray.index
            });
            // update blockText removing the first occurrence of tag found
            blockText = blockText.replace(matchArray[0], '');
            // remove encoded Tag from text for next scan
            contentState = Modifier.removeRange(
                contentState,
                selectionState,
                'forward',
            );
            // save blockRelative tag selection, where anchorOffset == focusOffset (collapsed)
            selectionState = contentState.getSelectionAfter();
            // build map to use next as block's point of split
            newLineMap.push({selectionState, blockKey})
        }
    });
    return {contentState, newLineMap};
};

export default removeNewLineInContentState;
