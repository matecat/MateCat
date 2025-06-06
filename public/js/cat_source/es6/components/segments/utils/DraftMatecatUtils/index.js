import getEntities from './getEntities'
import matchTag from './matchTag'
import encodeContent from './encodeContent'
import decodeSegment from './decodeSegment'
import duplicateFragment from './duplicateFragment'
import applyEntityToContentBlock from './applyEntityToContentBlock'
import insertFragment from './insertFragment'
import getEntitiesInFragment from './getEntitiesInFragment'
import createNewEntitiesFromMap from './createNewEntitiesFromMap'
import linkEntities from './linkEntities'
import beautifyEntities from './beautifyEntities'
import decodeTagInfo from './decodeTagInfo'
import replaceOccurrences from './replaceOccurrences'
import {formatText, getCharactersCounter} from './textUtils'
import buildFragmentFromJson from './buildFragmentFromJson'
import insertText from './insertText'
import updateEntityData from './updateEntityData'
import tagFromEntity from './tagFromEntity'
import matchTagInEditor from './matchTagInEditor'
import getSelectedText from './getSelectedText'
import addTagEntityToEditor from './addTagEntityToEditor'
import canDecorateRange from './canDecorateRange'
import getEntityStrategy from './getEntityStrategy'
import selectionIsEntity from './selectionIsEntity'
import insertEntityAtSelection from './insertEntityAtSelection'
import structFromName from './tagFromTagType'
import splitBlockAtSelection from './splitBlockAtSelection'
import getFragmentFromSelection from './DraftSource/src/component/handlers/edit/getFragmentFromSelection'
import buildFragmentFromText from './buildFragmentFromText'
import activateSearch from './activateSearch'
import activateLexiqa from './activateLexiqa'
import activateGlossary from './activateGlossary'
import activateQaCheckGlossary from './activateQaCheckGlossary'
import activateQaCheckBlacklist from './activateQaCheckBlacklist'
import prepareTextForLexiqa from './prepareTextForLexiqa'
import getSelectedTextWithoutEntities from './getSelectedTextWithoutEntities'
import replaceMultipleText from './replaceMultipleText'
import {
  transformTagsToHtml,
  transformTagsToText,
  decodeHtmlEntities,
  encodeHtmlEntities,
  decodePlaceholdersToPlainText,
  removeTagsFromText,
  autoFillTagsInTarget,
  hasDataOriginalTags,
  checkXliffTagsInText,
  removePlaceholdersForGlossary,
  excludeSomeTagsTransformToText,
} from './tagUtils'
import * as manageCaretPositionNearEntity from './manageCaretPositionNearEntity'

const DraftMatecatUtils = {
  // Tag utils
  removeTagsFromText,
  formatText,
  matchTag,
  decodeTagInfo,
  tagFromEntity,
  autoFillTagsInTarget,
  hasDataOriginalTags,
  checkXliffTagsInText,
  /*tagFromString,*/
  structFromName,
  // Entity Utils
  getEntityStrategy,
  getEntities,
  createNewEntitiesFromMap,
  linkEntities,
  beautifyEntities,
  applyEntityToContentBlock,
  updateEntityData,
  matchTagInEditor,
  addTagEntityToEditor,
  canDecorateRange,
  selectionIsEntity,
  manageCaretPositionNearEntity,
  insertEntityAtSelection,
  // Fragment Utils
  insertFragment,
  duplicateFragment,
  getEntitiesInFragment,
  buildFragmentFromJson,
  buildFragmentFromText,
  getFragmentFromSelection, // Duplicated from draft-js/lib, not part of the draft-js public API
  // Segment Utils
  encodeContent,
  decodeSegment,
  replaceOccurrences,
  // General
  insertText,
  getSelectedText,
  splitBlockAtSelection,
  // Decorators
  activateSearch,
  activateLexiqa,
  activateGlossary,
  activateQaCheckGlossary,
  activateQaCheckBlacklist,
  prepareTextForLexiqa,
  getCharactersCounter,
  getSelectedTextWithoutEntities,
  replaceMultipleText,
  transformTagsToHtml,
  transformTagsToText,
  decodeHtmlEntities,
  encodeHtmlEntities,
  decodePlaceholdersToPlainText,
  removePlaceholdersForGlossary,
  excludeSomeTagsTransformToText,
}

export default DraftMatecatUtils
