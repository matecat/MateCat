import {EditorState, Modifier, SelectionState} from 'draft-js'

const replaceMultipleText = (editorState, selectionsText = []) => {
  let updatedEditorState = editorState

  selectionsText.forEach(({start, end, value}) => {
    const contentState = updatedEditorState.getCurrentContent()
    const selections = []
    const blockMap = contentState.getBlockMap()

    blockMap.forEach((contentBlock) => {
      const blockKey = contentBlock.getKey()
      const blockSelection = SelectionState.createEmpty(blockKey).merge({
        anchorOffset: start,
        focusOffset: end,
      })
      selections.push(blockSelection)
    })

    let updatedContentState = contentState

    selections.forEach((selection) => {
      updatedContentState = Modifier.replaceText(contentState, selection, value)
    })

    updatedEditorState = EditorState.push(
      updatedEditorState,
      updatedContentState,
      'insert-characters',
    )
  })

  return updatedEditorState
}

export default replaceMultipleText
