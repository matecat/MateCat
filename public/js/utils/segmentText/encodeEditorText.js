import TextUtils from '../textUtils'
import {encodeHtmlEntities} from './tagUtils'

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
