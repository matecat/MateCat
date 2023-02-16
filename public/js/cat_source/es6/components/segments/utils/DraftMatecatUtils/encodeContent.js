import createNewEntitiesFromMap from './createNewEntitiesFromMap'
import linkEntities from './linkEntities'
import beautifyEntities from './beautifyEntities'
import {EditorState, SelectionState} from 'draft-js'
import splitOnTagPlaceholder from './splitOnTagPlaceHolder'
import removeNewLineInContentState from './removeNewLineInContentState'
import {getErrorCheckTag, getSplitBlockTag} from './tagModel'
import replaceOccurrences from './replaceOccurrences'

/**
 *
 * @param originalEditorState
 * @param plainText - text to analyze when editor is empty
 * @returns {*|EditorState} editorStateModified - An EditorState with all known tags treated as entities
 */
const encodeContent = (originalEditorState, plainText = '', sourceTagMap) => {
  // block history saving
  originalEditorState = EditorState.set(originalEditorState, {allowUndo: false})
  // get tag's types on which every block will be splitted
  const excludedTags = getSplitBlockTag()

  // sometimes there is no text between  <g id="n"> and </g> and backend merges them in <g id="n"/>
  // We have to split g tag selfclosed in g tag open and g tag closed
  plainText = plainText.replace(
    /&lt;g\sid="((?:(?!&gt;).)+?)"\s?\/&gt;/gi,
    '&lt;g id="$1"&gt;&lt;/g&gt;',
  )
  // Create entities
  const entitiesFromMap = createNewEntitiesFromMap(
    originalEditorState,
    excludedTags,
    plainText,
    sourceTagMap,
  )
  let {contentState, tagRange} = entitiesFromMap
  // Apply entities to EditorState
  let editorState = EditorState.push(
    originalEditorState,
    contentState,
    'apply-entity',
  )
  // NOTE: if you deactivate 'removeNewLineInContentState' and 'splitOnTagPlaceholder', remember to pass an empty
  // array as excludedTags to 'createNewEntitiesFromMap'. So every \n and \r will be showed as self-closed tags.

  // Remove LF or CR
  const {contentState: contentStateWithoutNewLines, newLineMap} =
    removeNewLineInContentState(editorState)
  editorState = EditorState.push(
    editorState,
    contentStateWithoutNewLines,
    'remove-range',
  )

  // Split blocks on LF or CR
  const contentSplitted = splitOnTagPlaceholder(editorState, newLineMap)
  editorState = EditorState.push(editorState, contentSplitted, 'split-block')
  // Link each openTag with its closure using entity key, otherwise tag are linked with openTagId/closeTagId
  //contentState = linkEntities(editorState);
  //editorState = EditorState.push(originalEditorState, contentState, 'change-block-data');

  // Replace each tag text with a placeholder
  // contentState = beautifyEntities(editorState);
  //editorState = beautifyEntities(editorState);
  //editorState = EditorState.push(editorState, contentState, 'insert-characters');

  // Unescape residual html entities after tag identification
  //editorState = replaceOccurrences(editorState, '&lt;', '<');
  //editorState = replaceOccurrences(editorState, '&gt;', '>');

  // Move selection at the end without focusing (for source)
  editorState = EditorState.moveSelectionToEnd(editorState)

  // allow history saving
  editorState = EditorState.set(editorState, {allowUndo: true})

  // Filter tags to remove nbsp, tab, CR, LF that will not be available in TagsMenu
  tagRange = tagRange.filter((tag) => {
    return getErrorCheckTag().includes(tag.data.name)
  })
  return {editorState, tagRange}
}

export default encodeContent
