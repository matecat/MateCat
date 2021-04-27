/**
 * Duplicated from draft-js - not part of the public API
 */

import getContentStateFragment from '../../../model/transaction/getContentStateFragment'

function getFragmentFromSelection(editorState) {
  const selectionState = editorState.getSelection()

  if (selectionState.isCollapsed()) {
    return null
  }

  return getContentStateFragment(
    editorState.getCurrentContent(),
    selectionState,
  )
}

export default getFragmentFromSelection
