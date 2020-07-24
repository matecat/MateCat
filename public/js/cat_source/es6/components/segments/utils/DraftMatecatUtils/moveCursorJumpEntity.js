import {EditorState} from "draft-js";


/**
 *
 * Replace the entire selection behavior made with left arrow or right arrow key (and optionally shiftKey).
 * When handling left or right arrow press, even if the command won't be handled in the end, the event fallback
 * behavior won't be called by the editor and the cursor remain at its initial position.
 * Here we check if there are entities to jump on key press or move the cursor accordingly to standard text-area behavior.
 *
 * @param editorState - initial EditorState
 * @param step - integer (-1 , 1) representing cursor step (backward or forward)
 * @param shift - shiftKey pressed
 * @returns editorState - EditorState with new selection forced
 */

const moveCursorJumpEntity = (editorState, step, shift = false) => {

    const selectionState = editorState.getSelection();
    const contentState = editorState.getCurrentContent();
    // ------ previous selection state
    const prevSelectionIsBackward = selectionState.getIsBackward();
    const prevAnchorOffset = selectionState.getAnchorOffset();
    const prevFocusOffset = selectionState.getFocusOffset();
    const anchorKey = selectionState.getAnchorKey();
    const focusKey = selectionState.getFocusKey();
    // ------ cursor position after moving
    const newCursorPosition = selectionState.getFocusOffset() + step;
    const currentBlock = contentState.getBlockForKey(focusKey);
    const currentBlockLength = currentBlock.getText().length;
    const nextPos = prevFocusOffset + step;
    // ------ new selection to merge
    let nextSelection = null;

    // find entities in block
    currentBlock.findEntityRanges(
        character => character.getEntity() !== null,
        (start, end) => {
            // get entity
            const entityKey = currentBlock.getEntityAt(start);
            const entity = contentState.getEntity(entityKey);
            // jump every immutable entity
            if (entity.getMutability() === 'IMMUTABLE' &&
                (start < newCursorPosition && end > newCursorPosition)) {
                nextSelection = {};
                nextSelection.nextAnchorKey = anchorKey;
                nextSelection.nextFocusKey = focusKey;
                if (step > 0) {
                    // jump on entity end
                    nextSelection.nextAnchorOffset = shift ? prevAnchorOffset : end;
                    nextSelection.nextFocusOffset = end;
                } else {
                    // jump on entity start
                    nextSelection.nextAnchorOffset = shift ? prevAnchorOffset : start;
                    nextSelection.nextFocusOffset = start;
                }
            }
        }
    );

    if (!nextSelection){
        nextSelection = {};
        if(step > 0){
            // go forward
            const nextBlockKey = contentState.getKeyAfter(focusKey);
            if(nextBlockKey && nextPos > currentBlockLength){
                const nextBlock = contentState.getBlockForKey(nextBlockKey);
                const nextBlockLength = nextBlock.getText().length;
                // Go on next block
                nextSelection.nextAnchorOffset = shift ? prevAnchorOffset : 0; // : start of next block
                nextSelection.nextAnchorKey = shift ? anchorKey : nextBlockKey; // : next block
                nextSelection.nextFocusOffset = shift && nextBlockLength > 0 ? 1 : 0;
                nextSelection.nextFocusKey = nextBlockKey;
                //  console.log('Go on next block');
            }else if(nextPos > currentBlockLength){
                // Go on block end
                nextSelection.nextAnchorOffset = shift ? prevAnchorOffset : currentBlockLength; // : end of block
                nextSelection.nextAnchorKey = anchorKey;
                nextSelection.nextFocusOffset = currentBlockLength;
                nextSelection.nextFocusKey = focusKey;
                // console.log('Go on block end');
            }else{
                // Go forward
                nextSelection.nextAnchorOffset = shift ? prevAnchorOffset : prevFocusOffset + step; // : go one step forward
                nextSelection.nextAnchorKey = shift ? anchorKey : focusKey;
                nextSelection.nextFocusOffset = prevFocusOffset + step;
                nextSelection.nextFocusKey = focusKey;
                // console.log('Go forward');
            }
        }else{
            // go back
            const previousBlockKey = contentState.getKeyBefore(focusKey);
            if(previousBlockKey && nextPos < 0){
                const previousBlock = contentState.getBlockForKey(previousBlockKey);
                const previousBlockLength = previousBlock.getText().length;
                // Go on previous block
                nextSelection.nextAnchorOffset = shift ? prevAnchorOffset : previousBlockLength; // : end of previous block
                nextSelection.nextAnchorKey = shift ? anchorKey : previousBlockKey;
                nextSelection.nextFocusOffset = shift && previousBlockLength > 0  ? previousBlockLength - 1 : previousBlockLength;
                nextSelection.nextFocusKey = previousBlockKey;
                // console.log('Go on previous block');
            }else if(nextPos < 0){
                // Go on block start
                nextSelection.nextAnchorOffset = shift ? prevAnchorOffset : 0; // fine del blocco precedente
                nextSelection.nextAnchorKey = anchorKey;
                nextSelection.nextFocusOffset = 0;
                nextSelection.nextFocusKey = focusKey;
                // console.log('Go on block start');
            }else{
                // Go back
                nextSelection.nextAnchorOffset = shift ? prevAnchorOffset : prevFocusOffset + step; // : go one step back
                nextSelection.nextAnchorKey = shift ? anchorKey : focusKey;
                nextSelection.nextFocusOffset = prevFocusOffset + step;
                nextSelection.nextFocusKey = focusKey;
                // console.log('Go back');
            }
        }
    }
    const newSelection = selectionState.merge({
        anchorOffset: nextSelection.nextAnchorOffset,
        anchorKey: nextSelection.nextAnchorKey,
        focusOffset: nextSelection.nextFocusOffset,
        focusKey: nextSelection.nextFocusKey,
        isBackward: checkIsBackward(nextSelection, prevSelectionIsBackward, shift, step > 0 )
    });
    // console.log(newSelection.serialize())
    return EditorState.forceSelection(
        editorState,
        newSelection
    );
};


/*
 * Comparing anchorOffset and focusOffset is made with their block-relative position. Even if the anchor is lower than focus
 * in document, it could have a block-relative offset higher than the offset of the focus.
 */
const checkIsBackward = (nextSelection, prevBackwardState, shift, forward) => {
    const {nextAnchorOffset, nextAnchorKey, nextFocusOffset, nextFocusKey} = nextSelection;
    // Same blocks && anchor > focus
    const cond1 = nextAnchorOffset > nextFocusOffset && nextAnchorKey === nextFocusKey;
    // Different blocks && anchor < focus
    const cond2 = nextAnchorKey !== nextFocusKey && nextAnchorOffset <= nextFocusOffset;
    const cond2Forward = nextAnchorKey !== nextFocusKey && nextAnchorOffset <= nextFocusOffset && prevBackwardState;
    // Different blocks && anchor > focus
    const cond3 = nextAnchorKey !== nextFocusKey && nextAnchorOffset >= nextFocusOffset && prevBackwardState;
    return !forward ? (shift && cond1 || cond2 || cond3) : (shift && cond1 || cond2Forward || cond3)
}

export default moveCursorJumpEntity;