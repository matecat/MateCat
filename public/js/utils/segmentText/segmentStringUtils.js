/**
 * Helpers that work on segment strings only (tags, placeholders, plain text).
 *
 * Code that renders or checks text without an editor imports this module.
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
  transformTagsToText,
}

export default SegmentStringUtils
