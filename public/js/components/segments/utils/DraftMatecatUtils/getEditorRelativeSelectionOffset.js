/**
 * Where to put something anchored to the current selection, in coordinates
 * relative to the editor rather than the viewport.
 *
 * The caret can sit closer to the editor's right edge than the element needs,
 * so the result is pulled left by the shortfall instead of overflowing.
 *
 * @param editorNode the editor's DOM node
 * @param minWidth   min length of element to show
 * @returns {{top: number, left: number}}
 */
export default function getEditorRelativeSelectionOffset(
  editorNode,
  minWidth = 300,
) {
  const editorBoundingRect = editorNode.getBoundingClientRect()
  const selectionBoundingRect = window
    .getSelection()
    .getRangeAt(0)
    .getBoundingClientRect()
  const leftInitial = selectionBoundingRect.x - editorBoundingRect.x
  const leftAdjusted =
    editorBoundingRect.right - selectionBoundingRect.left < minWidth
      ? leftInitial -
        (minWidth - (editorBoundingRect.right - selectionBoundingRect.left))
      : leftInitial

  // A selection with no geometry at all -- no layout yet, or a range the
  // browser cannot measure -- gets a fixed position rather than the origin,
  // so the element stays visible instead of landing in the corner.
  if (
    selectionBoundingRect.bottom === 0 &&
    selectionBoundingRect.left === 0 &&
    selectionBoundingRect.height === 0
  ) {
    return {
      top: 50,
      left: 50,
    }
  }

  return {
    top:
      selectionBoundingRect.bottom -
      editorBoundingRect.top +
      selectionBoundingRect.height,
    left: leftAdjusted,
  }
}
