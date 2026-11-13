// What the CodeMirror target editor (editor/matecat/TargetEditor.js) needs from the Draft side: the store, the QA
// warnings and the tag menu still read the translation in Draft's shapes (tag map, decoded text, missing tags, Lexiqa
// text), and the character counter and the toolbar's case change are Draft string utilities. Computed here with the
// same utilities Editarea uses, so the store sees exactly what it sees today. Deleted with Draft.
import {EditorState} from 'draft-js'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import {removeZeroWidthSpace} from './utils/DraftMatecatUtils/tagUtils'
import checkForMissingTags from './utils/DraftMatecatUtils/TagMenu/checkForMissingTag'

const encode = (translation, sourceTagMap) =>
  DraftMatecatUtils.encodeContent(
    EditorState.createEmpty(),
    translation,
    sourceTagMap,
  )

/**
 * What SegmentActions.updateTranslation takes for `translation` (a segment string), as Editarea computes it: the
 * plain text, the tag map, the source tags missing from it and the Lexiqa text.
 */
export const translationUpdate = (translation, sourceTagMap) => {
  const {editorState} = encode(translation, sourceTagMap)
  const {decodedSegment, entitiesRange} =
    DraftMatecatUtils.decodeSegment(editorState)
  const plainText = removeZeroWidthSpace(
    editorState.getCurrentContent().getPlainText(),
  )
  const tagMap = DraftMatecatUtils.matchTagInEditor(editorState, entitiesRange)
  const {missingTags} = checkForMissingTags(sourceTagMap, tagMap)
  return {
    plainText,
    tagMap,
    missingTags,
    lxqDecodedTranslation:
      DraftMatecatUtils.prepareTextForLexiqa(decodedSegment),
  }
}

/** The character counter of `translation`; `countTags`: the project counts g, bx, ex and x tags as characters. */
export const charactersCounter = (translation, countTags) =>
  DraftMatecatUtils.getCharactersCounter(
    countTags
      ? DraftMatecatUtils.excludeSomeTagsTransformToText(translation, [
          'g',
          'bx',
          'ex',
          'x',
        ])
      : DraftMatecatUtils.decodePlaceholdersToPlainText(
          DraftMatecatUtils.removeTagsFromText(translation),
        ),
  )

/**
 * The Draft id and placeholder of every tag of `translation` the source can highlight, in document order, with its
 * markup: `[{raw, id, placeholder}]`.
 */
export const tagIdentities = (translation, sourceTagMap) =>
  encode(translation, sourceTagMap)
    .tagRange.slice()
    .sort((a, b) => a.offset - b.offset)
    .map(({data}) => ({
      raw: data.encodedText,
      id: data.id,
      placeholder: data.placeholder,
    }))

export const targetEditorBridge = {
  translationUpdate,
  charactersCounter,
  tagIdentities,
  formatText: DraftMatecatUtils.formatText,
}
