const selectionIsEntity = (editorState) => {
  const contentState = editorState.getCurrentContent()
  const currentSelection = editorState.getSelection()
  const anchorKey = currentSelection.getAnchorKey()
  const focusKey = currentSelection.getFocusKey()
  //entity is never on two different block
  if (focusKey !== anchorKey) return {entityKey: null, onEdge: null}
  const anchorOffset = currentSelection.getAnchorOffset()
  const anchorBlock = contentState.getBlockForKey(anchorKey)
  let anchorEntityKey = anchorBlock && anchorBlock.getEntityAt(anchorOffset)
  // if selection is collapsed if selection on edge
  let onEdge = false
  if (anchorEntityKey && currentSelection.isCollapsed()) {
    anchorBlock.findEntityRanges(
      (character) => {
        return anchorEntityKey === character.getEntity()
      },
      (start, end) => {
        // if on entity's edge, entity is not selected
        if (anchorOffset === start || anchorOffset === end) {
          onEdge = true
        }
      },
    )
  }
  return {entityKey: anchorEntityKey, onEdge}
}

export default selectionIsEntity
