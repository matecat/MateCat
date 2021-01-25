import {EditorState, SelectionState} from "draft-js";
import selectionIsEntity from "./selectionIsEntity";


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

const moveCursorJumpEntity = (editorState, step, shift = false, isRTL) => {

    const selectionState = editorState.getSelection();
    const contentState = editorState.getCurrentContent();

    // ------ previous selection state
    const prevSelectionIsBackward = selectionState.getIsBackward();
    const prevAnchorOffset = selectionState.getAnchorOffset();
    const prevFocusOffset = selectionState.getFocusOffset();
    const anchorKey = selectionState.getAnchorKey();
    const focusKey = selectionState.getFocusKey();

    // ------ cursor position after moving
    let newCursorPosition = selectionState.getFocusOffset() + step; // +1 / -1
    const currentBlock = contentState.getBlockForKey(focusKey);
    const currentBlockLength = currentBlock.getText().length;
    const nextPos = prevFocusOffset + step;

    // ------ new selection to merge
    let nextSelection = null;
    let newSelection = null;

    const start = selectionState.getStartOffset();
    const selectedText = step>0 ? currentBlock.getText().slice(start, start +1) : currentBlock.getText().slice(start-1 , start);
    const checkZeroWidthSpace = String.fromCharCode(parseInt('200B',16)) === selectedText;
    if(checkZeroWidthSpace){
        newCursorPosition = step>0 ?  newCursorPosition +1 : newCursorPosition -1;
    }


    // find entities in block
    currentBlock.findEntityRanges(
        character => character.getEntity() !== null,
        (start, end) => {
            // get entity
            const entityKey = currentBlock.getEntityAt(start);
            const entity = contentState.getEntity(entityKey);
            // jump every immutable entity
            const goingBack = start <= newCursorPosition && (step < 0) && end > newCursorPosition;
            const goingForward = start < newCursorPosition && end > newCursorPosition;
            const jumpingSingleCharEntity = (start < newCursorPosition) && (end >= newCursorPosition) && ((end - start) === 1)

            if (entity.getMutability() === 'IMMUTABLE' &&
                // if you cursor inside entity
                (goingBack || goingForward || jumpingSingleCharEntity)
                // nothing already jumped
                && nextSelection === null) {

                nextSelection = {};
                nextSelection.nextAnchorKey = anchorKey; // same
                nextSelection.nextFocusKey = focusKey; // same

                if (step > 0) {
                    // jump on entity end
                    nextSelection.nextAnchorOffset = shift ? prevAnchorOffset : end+1;
                    nextSelection.nextFocusOffset = end+1;
                } else {
                    // jump on entity start
                    nextSelection.nextAnchorOffset = shift ? prevAnchorOffset : start-1;
                    nextSelection.nextFocusOffset = start-1;
                }
            }
        }
    );

    newSelection = nextSelection ?
        SelectionState.createEmpty(nextSelection.nextAnchorKey).merge({
            anchorOffset: nextSelection.nextAnchorOffset,
            focusOffset: nextSelection.nextFocusOffset,
            focusKey: nextSelection.nextFocusKey,
            isBackward: checkIsBackward(nextSelection, prevSelectionIsBackward, shift, step > 0 )
        }) : null

    return newSelection ? EditorState.forceSelection(
        editorState,
        newSelection
    ) : newSelection
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