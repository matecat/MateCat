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
import {
  getXliffRegExpression,
  getIdAttributeRegEx,
  cleanSegmentString,
  unescapeHTML,
  unescapeHTMLLeaveTags,
  decodeTagsToPlainText,
  formatText,
  getCharactersCounter,
  unescapeHTMLinTags,
} from './textUtils'
import buildFragmentFromJson from './buildFragmentFromJson'
import insertText from './insertText'
import updateEntityData from './updateEntityData'
import tagFromEntity from './tagFromEntity'
import matchTagInEditor from './matchTagInEditor'
import getSelectedText from './getSelectedText'
import addTagEntityToEditor from './addTagEntityToEditor'
import canDecorateRange from './canDecorateRange'
import getEntityStrategy from './getEntityStrategy'
import moveCursorJumpEntity from './moveCursorJumpEntity'
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

const DraftMatecatUtils = {
  // Text utils
  cleanSegmentString,
  getXliffRegExpression,
  getIdAttributeRegEx,
  unescapeHTML,
  unescapeHTMLinTags,
  unescapeHTMLLeaveTags,
  formatText,
  // Tag Utils
  matchTag,
  decodeTagInfo,
  tagFromEntity,
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
  moveCursorJumpEntity,
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
  decodeTagsToPlainText,
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
}

export default DraftMatecatUtils
