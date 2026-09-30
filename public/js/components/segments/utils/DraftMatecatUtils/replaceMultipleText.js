import {EditorState, Modifier, SelectionState} from 'draft-js'

const replaceMultipleText = (editorState, selectionsText = []) => {
  let updatedEditorState = editorState

  // The offsets in selectionsText are relative to the block the selection starts
  // in (that is the block getSelectedTextWithoutEntities walked), so every
  // replacement must target that block. Applying them to each block of the
  // content instead left the selected text untouched and wrote the replacement
  // into the last block — a target ending with a newline tag has an extra block,
  // so uppercasing a word appended it at the end instead of replacing it.
  const blockKey = editorState.getSelection().getStartKey()

  selectionsText.forEach(({start, end, value}) => {
    const contentState = updatedEditorState.getCurrentContent()
    const blockSelection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: start,
      focusOffset: end,
    })

    const updatedContentState = Modifier.replaceText(
      contentState,
      blockSelection,
      value,
    )

    updatedEditorState = EditorState.push(
      updatedEditorState,
      updatedContentState,
      'insert-characters',
    )
  })

  return updatedEditorState
}

export default replaceMultipleText
