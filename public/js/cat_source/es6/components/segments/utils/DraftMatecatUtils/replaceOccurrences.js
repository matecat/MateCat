import _ from 'lodash'
import {SelectionState, Modifier, EditorState} from 'draft-js'
import TextUtils from '../../../../utils/textUtils'
/**
 *
 * @param editorState
 * @param oldText - Text to replace
 * @param newText - Text to insert
 * @param index - The occurrence index to replace, if not replace all
 * @returns {editorState}

 *
 */
const replaceOccurrences = (editorState, oldText, newText, index) => {
  const regex = new RegExp(TextUtils.escapeRegExp(oldText), 'gi')
  //recupero editor state
  const selectionsToReplace = []
  //recuper la mappa dei blocchi
  const blockMap = editorState.getCurrentContent().getBlockMap()

  const findWithRegex = (regex, contentBlock, callback) => {
    const text = contentBlock.getText()
    let matchArr, start, end
    while ((matchArr = regex.exec(text)) !== null) {
      start = matchArr.index
      end = start + matchArr[0].length
      callback(start, end)
    }
  }
  let indexToReplace = 0
  //Itero i blocchi alla ricerca del termine da sostituire
  blockMap.forEach((contentBlock) => {
    findWithRegex(regex, contentBlock, (start, end) => {
      //recuper la chiave del blocco
      const blockKey = contentBlock.getKey()
      //Creo la selection del blocco
      const blockSelection = SelectionState.createEmpty(blockKey).merge({
        anchorOffset: start,
        focusOffset: end,
      })
      if (
        _.isUndefined(index) ||
        (!_.isUndefined(index) && indexToReplace === index)
      ) {
        selectionsToReplace.push(blockSelection)
      }
      indexToReplace++
    })
  })

  let contentState = editorState.getCurrentContent()
  let lengthDiff = 0
  //Itero i blocchi dove è presente il termine da sostituire e applico il Modifier
  selectionsToReplace.forEach((selectionState) => {
    let newSel = selectionState.merge({
      anchorOffset: selectionState.anchorOffset - lengthDiff,
      focusOffset: selectionState.focusOffset - lengthDiff,
    })
    contentState = Modifier.replaceText(contentState, newSel, newText)
    lengthDiff +=
      selectionState.focusOffset - selectionState.anchorOffset - newText.length
  })

  return EditorState.push(editorState, contentState)
}

export default replaceOccurrences
