import {Modifier, SelectionState} from 'draft-js'

/**
 *
 * @param editorState - the current EditorState
 * @param newLineMap - an array of {blockKey, selectionState} of each \n or \r in the ContentState.
 * @returns contentState - the new ContentState with splitted ContentBlocks
 */
const splitOnTagPlaceholder = (editorState, newLineMap) => {
  let contentState = editorState.getCurrentContent()
  if (!newLineMap) return contentState

  newLineMap.sort((a, b) => {
    return b.selectionState.anchorOffset - a.selectionState.anchorOffset
  })

  while (newLineMap.length > 0) {
    let blocks = contentState.getBlockMap()
    // take one of the available tags
    const {blockKey, selectionState} = newLineMap.pop()

    // start splitting
    blocks.forEach((contentBlock) => {
      if (blockKey === contentBlock.getKey()) {
        contentState = Modifier.splitBlock(contentState, selectionState)
        const currentBlock = contentState.getBlockForKey(blockKey)
        const newBlock = contentState.getBlockAfter(blockKey)
        const newBlockKey = newBlock.getKey()

        newLineMap.forEach((newline) => {
          // if it is a newLinesTag on the same block previously splitted
          if (
            newline.blockKey === blockKey &&
            newline.selectionState.anchorOffset > selectionState.anchorOffset
          ) {
            // update selection to match newly created block
            // residual newLinesTag will be on the new block
            const newAnchorOffset =
              newline.selectionState.anchorOffset -
              currentBlock.getText().length
            const newFocusOffset =
              newAnchorOffset +
              (newline.selectionState.focusOffset -
                newline.selectionState.anchorOffset)

            const newSelectionState = SelectionState.createEmpty(
              newBlockKey,
            ).merge({
              anchorOffset: newAnchorOffset,
              focusOffset: newFocusOffset,
            })

            // update residual newLinesTag blockKey and selectionState
            newline.blockKey = newBlockKey
            newline.selectionState = newSelectionState
          }
        })
      }
    })
  }
  return contentState
}

export default splitOnTagPlaceholder
