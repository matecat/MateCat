import {EditorState} from "draft-js";

const moveCursorJumpEntity = (editorState, offset, shift = false) => {
    const selectionState = editorState.getSelection();
    const contentState = editorState.getCurrentContent();
    // cursor position after moving
    const newCursorPosition = selectionState.getFocusOffset() + offset;
    const block = contentState.getBlockForKey(selectionState.getAnchorKey())
    // new selection to merge
    let newSelection = null;
    // find entities
    block.findEntityRanges(
        character => character.getEntity() !== null,
        (start, end) => {
            // get entity
            const entityKey = block.getEntityAt(start);
            const entity = contentState.getEntity(entityKey)
            // jump every immutable entity
            if (entity.getMutability() === 'IMMUTABLE' && (start < newCursorPosition && end > newCursorPosition)) {
                if (offset > 0) {
                    // jump on entity end
                    newSelection = selectionState.merge({
                        anchorOffset: shift ? selectionState.getAnchorOffset() : end,
                        focusOffset: end,
                        isBackward: false
                    });
                } else {
                    // jump on entity start
                    newSelection = selectionState.merge({
                        anchorOffset: shift ? selectionState.getAnchorOffset() : start,
                        focusOffset: start,
                        isBackward: shift
                    });
                }
            }
        }
    );

    if (!newSelection) {
        newSelection = selectionState.merge({
            anchorOffset: shift ? selectionState.getAnchorOffset() : newCursorPosition,
            focusOffset: newCursorPosition,
        });
    }
    // save new selection
    const newEditorState = EditorState.forceSelection(
        editorState,
        newSelection,
    );
    return newEditorState;
};

export default moveCursorJumpEntity;