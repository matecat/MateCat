import getEntities from './getEntities'
import TextUtils from '../../../../utils/textUtils'
import {encodeHtmlEntities} from './tagUtils'
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

/**
 * Turns editor text, whose tag entities are already replaced by their
 * `encodedText`, into a segment string: line breaks become the line feed
 * placeholder and the text is HTML-encoded, the tags left as they are.
 */
export const encodeEditorText = (plainEditorText) => {
  const decodedSegmentPlain = plainEditorText
    .replace(/\n/gi, config.lfPlaceholder)
    .replace(new RegExp(String.fromCharCode(parseInt('200B', 16)), 'gi'), '')
  const {tags, text} = TextUtils.replaceTempTags(decodedSegmentPlain)
  return TextUtils.restoreTempTags(tags, encodeHtmlEntities(text))
}

export default decodeSegment
