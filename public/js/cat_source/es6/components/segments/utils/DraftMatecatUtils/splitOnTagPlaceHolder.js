import {
    Modifier,
    SelectionState,
} from 'draft-js';

/**
 *
 * @param editorState - the current EditorState
 * @param newLineMap - an array of {blockKey, selectionState} of each \n or \r in the ContentState.
 * @returns contentState - the new ContentState with splitted ContentBlocks
 */
const splitOnTagPlaceholder = (editorState, newLineMap) => {

    let contentState = editorState.getCurrentContent();
    if(!newLineMap) return contentState;

    while(newLineMap.length > 0){
        let blocks = contentState.getBlockMap();
        // take one of the available tags
        const {blockKey, selectionState} = newLineMap.pop();
        // start splittinh
        blocks.forEach((contentBlock) => {
            if (blockKey === contentBlock.getKey()){
                contentState = Modifier.splitBlock(
                    contentState,
                    selectionState
                );
                const newBlock = contentState.getBlockAfter(contentBlock.getKey())
                newLineMap.forEach( newline => {
                    // if it is a newLinesTag on the same block previously splitted
                    if(newline.blockKey === blockKey && newline.selectionState.anchorOffset > selectionState.anchorOffset){
                        // update selection to match newly created block
                        // residual newLinesTag will be on the new block
                        const newAnchorOffset = newline.selectionState.anchorOffset - contentBlock.getText().length;
                        const newFocusOffset = newAnchorOffset + (newline.selectionState.focusOffset - newline.selectionState.anchorOffset)
                        const newSelectionState = new SelectionState({
                            anchorKey: newBlock.getKey(),
                            anchorOffset: newAnchorOffset,
                            focusKey: newBlock.getKey(),
                            focusOffset: newFocusOffset
                        });
                        // update residual newLinesTag blockKey and selectionState
                        newline.blockKey = newBlock.getKey();
                        newline.selectionState = newSelectionState;
                    }
                })
            }
        });
    }
    return contentState;
};

export default splitOnTagPlaceholder;
