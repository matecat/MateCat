/**
 * Helpers that work on segment strings only (tags, placeholders, plain text).
 *
 * Nothing here depends on Draft.js, so code that renders or checks text without
 * an editor imports this module instead of the DraftMatecatUtils index, which
 * pulls in the whole editor layer.
 */
import {
  autoFillTagsInTarget,
  checkXliffTagsInText,
  decodePlaceholdersToPlainText,
  excludeSomeTagsFromText,
  excludeSomeTagsTransformToText,
  hasDataOriginalTags,
  removePlaceholdersForGlossary,
  removeTagsFromText,
  transformTagsToHtml,
  transformTagsToText,
} from './tagUtils'
import {getCharactersCounter} from './textUtils'

const SegmentStringUtils = {
  autoFillTagsInTarget,
  checkXliffTagsInText,
  decodePlaceholdersToPlainText,
  excludeSomeTagsFromText,
  excludeSomeTagsTransformToText,
  getCharactersCounter,
  hasDataOriginalTags,
  removePlaceholdersForGlossary,
  removeTagsFromText,
  transformTagsToHtml,
  transformTagsToText,
}

export default SegmentStringUtils
