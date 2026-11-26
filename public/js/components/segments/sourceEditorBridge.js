// What the CodeMirror source editor (editor/matecat/SourceEditor.js) needs from the Draft side while the target is
// still a Draft editor: the Draft target reads the source tag map, decoded text and Lexiqa text from the store in
// Draft's own shapes, and matches a clicked tag by Draft's tag id and placeholder. Computed here with the same Draft
// utilities SegmentSource uses, so the Draft target sees exactly what it sees today. Deleted with Draft.
import {EditorState} from 'draft-js'
import DraftMatecatUtils from './utils/DraftMatecatUtils'
import {removeZeroWidthSpace} from './utils/DraftMatecatUtils/tagUtils'
import SegmentActions from '../../actions/SegmentActions'

const encode = (source) =>
  DraftMatecatUtils.encodeContent(EditorState.createEmpty(), source)

/** Pushes `source` (a segment string) into the store as SegmentSource does: decoded, plain, tag map, Lexiqa text. */
export const publishSource = (sid, source) => {
  if (source === '') return
  const {editorState, tagRange} = encode(source)
  const plainText = removeZeroWidthSpace(
    editorState.getCurrentContent().getPlainText(),
  )
  const {decodedSegment} = DraftMatecatUtils.decodeSegment(editorState)
  SegmentActions.updateSource(
    sid,
    decodedSegment,
    plainText,
    tagRange,
    DraftMatecatUtils.prepareTextForLexiqa(decodedSegment),
  )
}

/**
 * The Draft id and placeholder of every tag of `source` that the Draft target can highlight, in document order, with
 * its markup: `[{raw, id, placeholder}]`.
 */
export const tagIdentities = (source) =>
  encode(source)
    .tagRange.slice()
    .sort((a, b) => a.offset - b.offset)
    .map(({data}) => ({
      raw: data.encodedText,
      id: data.id,
      placeholder: data.placeholder,
    }))

export const sourceEditorBridge = {publishSource, tagIdentities}
