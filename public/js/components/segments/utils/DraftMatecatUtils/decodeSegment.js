import getEntities from './getEntities'
import {encodeEditorText} from '../../../../utils/segmentText/encodeEditorText'
/**
 *
 * @param editorState
 * @returns {}
 */
const decodeSegment = (editorState) => {
  let contentState = editorState.getCurrentContent()
  if (!contentState.hasText())
    return {entities: [], decodedSegment: contentState.getPlainText()}
  const entities = getEntities(editorState) // already consecutive
  // Adapt offset from block to absolute
  const blocks = contentState.getBlockMap()
  let plainEditorText = contentState.getPlainText()
  let totalBlocksLength = 0
  let slicedLength = 0
  blocks.forEach((block) => {
    const blockKey = block.getKey()
    entities.forEach((tagEntity) => {
      if (tagEntity.blockKey === blockKey) {
        const {encodedText} = tagEntity.entity.data
        // add previous block length and previous replace length diff
        const start = tagEntity.start + totalBlocksLength - slicedLength
        const end = tagEntity.end + totalBlocksLength - slicedLength
        plainEditorText =
          plainEditorText.slice(0, start) +
          encodedText +
          plainEditorText.slice(end)
        slicedLength += end - start - encodedText.length
      }
    })
    // Block length plus newline char
    totalBlocksLength += block.getLength() + 1
  })

  return {
    entitiesRange: entities,
    decodedSegment: encodeEditorText(plainEditorText),
  }
}

// encodeEditorText moved to utils/segmentText; re-exported for the Draft code until Draft is removed (M5).
export {encodeEditorText}

export default decodeSegment
