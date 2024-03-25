import {each, cloneDeep} from 'lodash'

import getEntities from './getEntities'
import {isToReplaceForLexiqa} from './tagModel'

const updateLexiqaWarnings = (editorState, warnings) => {
  const contentState = editorState.getCurrentContent()
  const blocks = contentState.getBlockMap()
  let maxCharsInBlocks = 0
  let updatedWarnings = []
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
    each(warnings, (warn) => {
      // Todo: warnings between 2 block are now ignored
      const alreadyScannedChars =
        maxCharsInBlocks - loopedContentBlock.getLength()
      // remove offset added by '<' and '>' that wrap tags preceding current warning
      // entitiesInBlock.forEach((ent) => {
      //   if (
      //     ent.start + alreadyScannedChars < warn.start &&
      //     ent.end + alreadyScannedChars <= warn.start
      //   ) {
      //     if (!isToReplaceForLexiqa(ent.entity.getData().name)) {
      //       warn.start -= 2
      //       warn.end -= 2
      //     }
      //   }
      // })
      if (
        warn.start < maxCharsInBlocks &&
        warn.end <= maxCharsInBlocks &&
        (warn.start >= alreadyScannedChars ||
          warn.start === alreadyScannedChars - 1)
      ) {
        // Lexiqa warning length isn't valid, recompute length based on offset
        let warnLength = warn.end - warn.start
        let relativeStart = warn.start - alreadyScannedChars
        // Strings passed to lexiqa includes newlines so if two spaces are at the end of the line,
        // they will be paired with newline char, causing lexiqa error to be of length 3.
        // Same occurs if spaces are placed at the begininning of a new block: they will be paired with previous
        // block's newline. Here we correct the last case, forcing error to start on the new block and ignoring newline char
        if (warn.start === alreadyScannedChars - 1) {
          warnLength -= 1 // delete newline
          relativeStart += 1 // start on next block
        }
        const relativeEnd = relativeStart + warnLength
        const warnUpdated = cloneDeep(warn)
        warnUpdated.start = relativeStart // to remove '<' and '>' that wrap
        warnUpdated.end = relativeEnd
        // Add blockKey needed in decorator to find correct warning
        warnUpdated.blockKey = loopedBlockKey
        updatedWarnings.push(warnUpdated)
      }
    })
  })
  return updatedWarnings
}

export default updateLexiqaWarnings
