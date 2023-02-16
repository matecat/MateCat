import {Modifier, SelectionState} from 'draft-js'

/**
 *
 * @param editorState - the current EditorState
 * @returns {{contentState: *, newLineMap: []}} - the new ContentState in which every _0A or _0D  tag is replaced with ''
 * and an array with all tags removed mapped as {blockKey, selectionState}
 */
const removeNewLineInContentState = (editorState) => {
  let contentState = editorState.getCurrentContent()
  let newLineMap = []
  let blocks = contentState.getBlockMap()

  const lineFeedRegex = /##\$(_0A)\$##/gi
  const carriageReturnRegex = /##\$(_0D)\$##/gi
  const mixedCRLFRegex = /##\$_0D\$####\$_0A\$##/g

  // start replacing
  blocks.forEach((contentBlock) => {
    // get block key
    const blockKey = contentBlock.getKey()
    // get block plain text
    let blockTextRe = contentBlock.getText()
    let matchArray

    // 1 - find crlf
    const crlfArr = []
    blockTextRe = blockTextRe.replace(mixedCRLFRegex, (match, offset) => {
      crlfArr.push({offset: offset, type: '##$_0D$####$_0A$##', blockKey})
      return '##$_CR$####$_LF$##' // replace to avoid other regex to match with partial text
    })

    // 2- find lf
    const lfArr = []
    while ((matchArray = lineFeedRegex.exec(blockTextRe)) !== null) {
      lfArr.push({offset: matchArray.index, type: '##$_0A$##'})
    }

    // 3- cr
    const crArr = []
    while ((matchArray = carriageReturnRegex.exec(blockTextRe)) !== null) {
      crArr.push({offset: matchArray.index, type: '##$_0D$##'})
    }

    // Sort descending to respect replace order
    const splitPointArr = [...crlfArr, ...lfArr, ...crArr].sort(
      (a, b) => b.offset - a.offset,
    )

    while (splitPointArr.length > 0) {
      const splitPoint = splitPointArr.pop()
      // set selection on tag
      let selectionState = SelectionState.createEmpty(blockKey).merge({
        anchorOffset: splitPoint.offset,
        focusOffset: splitPoint.type.length + splitPoint.offset,
      })
      // remove encoded Tag from text for next scan
      contentState = Modifier.removeRange(
        contentState,
        selectionState,
        'forward',
      )
      // update residual splitpoints offset by removing the previous one
      splitPointArr.forEach((sp) => (sp.offset -= splitPoint.type.length))
      // save blockRelative tag selection, where anchorOffset == focusOffset (collapsed)
      selectionState = contentState.getSelectionAfter()
      // build map to use next as block's point of split
      newLineMap.push({selectionState, blockKey})
    }
  })

  return {contentState, newLineMap}
}

export default removeNewLineInContentState
