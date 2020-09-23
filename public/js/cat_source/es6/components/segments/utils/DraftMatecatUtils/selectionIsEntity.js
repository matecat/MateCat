const selectionIsEntity = (editorState) => {
    const contentState = editorState.getCurrentContent();
    const currentSelection = editorState.getSelection();
    const anchorKey = currentSelection.getAnchorKey();
    const focusKey = currentSelection.getFocusKey();
    //entity is never on two different block
    if(focusKey !== anchorKey) return null;
    const anchorOffset =  currentSelection.getAnchorOffset();
    const anchorBlock = contentState.getBlockForKey(anchorKey);
    let anchorEntityKey = anchorBlock.getEntityAt(anchorOffset);
    // if selection is collapsed if selection on edge
    if(anchorEntityKey && currentSelection.isCollapsed()){
        anchorBlock.findEntityRanges(
            (character) => {
                return anchorEntityKey === character.getEntity()
            },
            (start, end) => {
                // if on entity's edge, entity is not selected
                if(anchorOffset === start || anchorOffset === end){
                    anchorEntityKey = null;
                }
            }
        );
    }
    return anchorEntityKey;
};

export default selectionIsEntity;