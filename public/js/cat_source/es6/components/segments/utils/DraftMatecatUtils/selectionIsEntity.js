const selectionIsEntity = (editorState) => {
    const contentState = editorState.getCurrentContent();
    const selectionKey = editorState.getSelection().getAnchorKey();
    const selectionOffset =  editorState.getSelection().getAnchorOffset();
    const block = contentState.getBlockForKey(selectionKey);
    return block.getEntityAt(selectionOffset);
};

export default selectionIsEntity;