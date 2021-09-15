import getEntities from './getEntities'
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

  let decodedSegmentPlain = plainEditorText
    .replace(/\n/gi, config.lfPlaceholder)
    .replace(new RegExp(String.fromCharCode(parseInt('200B', 16)), 'gi'), '')

  return {entitiesRange: entities, decodedSegment: decodedSegmentPlain}
}

export default decodeSegment
