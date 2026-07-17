import getFragmentFromSelection from './DraftSource/src/component/handlers/edit/getFragmentFromSelection'
import encodeContent from './encodeContent'
import {EditorState} from 'draft-js'

/**
 *
 * @param plainText
 * @returns OrderedMap - the fragment created with the plainText
 *
 * Here we create a new EditorState just to exploit getFragmentFromSelection and be sure to have a full working fragment.
 *
 */
const buildFragmentFromText = (plainText) => {
  // encode plain text
  const emptyEditorState = EditorState.createEmpty()
  const plainEditorStateEncoded = encodeContent(emptyEditorState, plainText)
  let {editorState: clipboardEditorState} = plainEditorStateEncoded
  const contentState = clipboardEditorState.getCurrentContent()
  // select all content
  const selectAll = clipboardEditorState.getSelection().merge({
    anchorKey: contentState.getFirstBlock().getKey(),
    anchorOffset: 0,
    focusOffset: contentState.getLastBlock().getText().length,
    focusKey: contentState.getLastBlock().getKey(),
  })
  // force selection on all content
  clipboardEditorState = EditorState.forceSelection(
    clipboardEditorState,
    selectAll,
  )
  // get fragment
  return getFragmentFromSelection(clipboardEditorState)
}

export default buildFragmentFromText
