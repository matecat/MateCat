import encodeContent from './encodeContent'
import {EditorState} from 'draft-js'

/**
 * @param plainText a segment string
 * @returns OrderedMap - the blocks of the segment, its tags as entities, ready
 *   for Modifier.replaceWithFragment; null when there is no text. The content
 *   is built fresh on every call, so its block keys are new.
 */
const buildFragmentFromText = (plainText) => {
  const {editorState} = encodeContent(EditorState.createEmpty(), plainText)
  const contentState = editorState.getCurrentContent()
  return contentState.hasText() ? contentState.getBlockMap() : null
}

export default buildFragmentFromText
