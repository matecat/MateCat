import getEntities from './getEntities'
import {isToReplaceForLexiqa} from './tagModel'

const transformLexiqaPoints = (editorState, start, end) => {
  const contentState = editorState.getCurrentContent()
  const blocks = contentState.getBlockMap()
  let maxCharsInBlocks = 0
  const result = {}
  const entities = getEntities(editorState)
  blocks.forEach((loopedContentBlock) => {
    const firstBlockKey = contentState.getFirstBlock().getKey()
    const loopedBlockKey = loopedContentBlock.getKey()
    // Add current block length
    const newLineChar = loopedBlockKey !== firstBlockKey ? 1 : 0
    maxCharsInBlocks += loopedContentBlock.getLength() + newLineChar
    const entitiesInBlock = entities.filter(
      (ent) => ent.blockKey === loopedBlockKey,
    )
    // Todo: warnings between 2 block are now ignored
    const alreadyScannedChars =
      maxCharsInBlocks - loopedContentBlock.getLength()
    // remove offset added by '<' and '>' that wrap tags preceding current warning
    entitiesInBlock.forEach((ent) => {
      if (
        ent.start + alreadyScannedChars < start &&
        ent.end + alreadyScannedChars <= start
      ) {
        if (!isToReplaceForLexiqa(ent.entity.getData().name)) {
          start -= 2
          end -= 2
        }
      }
    })
    if (
      start < maxCharsInBlocks &&
      end <= maxCharsInBlocks &&
      (start >= alreadyScannedChars || start === alreadyScannedChars - 1)
    ) {
      // Lexiqa warning length isn't valid, recompute length based on offset
      let warnLength = end - start
      let relativeStart = start - alreadyScannedChars
      // Strings passed to lexiqa includes newlines so if two spaces are at the end of the line,
      // they will be paired with newline char, causing lexiqa error to be of length 3.
      // Same occurs if spaces are placed at the begininning of a new block: they will be paired with previous
      // block's newline. Here we correct the last case, forcing error to start on the new block and ignoring newline char
      if (start === alreadyScannedChars - 1) {
        warnLength -= 1 // delete newline
        relativeStart += 1 // start on next block
      }
      const relativeEnd = relativeStart + warnLength
      result.start = relativeStart
      result.end = relativeEnd
    }
  })
  return result
}

export default transformLexiqaPoints
