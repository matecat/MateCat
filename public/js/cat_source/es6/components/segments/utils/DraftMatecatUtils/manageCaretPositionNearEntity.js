import {EditorState, SelectionState} from 'draft-js'
import getEntities from './getEntities'

const getEntityContainer = (classNameToMatch) => {
  const selection = window.getSelection()
  if (!selection) return

  let container

  const iterate = (node = selection.focusNode) => {
    if (
      node &&
      typeof node.getAttribute === 'function' &&
      node.getAttribute('contenteditable') === 'true'
    )
      return

    if (node?.classList?.contains(classNameToMatch)) {
      container = node
    } else if (node) {
      iterate(node.parentNode)
    }
  }

  iterate()
  return container
}

const getTextNode = (element) => {
  let textNode

  const iterate = (node = element) => {
    if (!node) return

    if (node.nodeName === '#text') {
      textNode = node
    } else if (node) {
      iterate(node.firstChild)
    }
  }

  iterate()
  return textNode
}

export const checkCaretIsNearEntity = ({editorState, direction = 'right'}) => {
  const selection = editorState.getSelection()

  const start = selection.getStartOffset()
  const end = selection.getEndOffset()

  const entities = getEntities(editorState)
  const entityMatched = entities.find((entity) =>
    direction === 'left'
      ? start <= entity.end && start > entity.start
      : end < entity.end && end >= entity.start,
  )

  return entityMatched
    ? moveCaretOutsideEntity({editorState, entity: entityMatched, direction})
    : undefined
}

export const checkCaretIsNearZwsp = ({editorState, direction = 'right'}) => {
  const selection = editorState.getSelection()
  const contentState = editorState.getCurrentContent()
  const focusKey = selection.getFocusKey()
  const currentBlock = contentState.getBlockForKey(focusKey)

  const start = selection.getStartOffset()
  const end = selection.getEndOffset()

  if (start !== end) return

  const point = {
    ...(direction === 'left'
      ? {start: start - 1, end: start}
      : {start, end: start + 1}),
  }
  const zwsp = String.fromCharCode(parseInt('200B', 16))
  const textPortion = currentBlock.getText().slice(point.start, point.end)

  if (textPortion === zwsp) {
    const pointOffset = direction === 'left' ? start - 1 : start + 1

    const updatedSelection = SelectionState.createEmpty(focusKey).merge({
      anchorOffset: pointOffset,
      focusOffset: pointOffset,
      focusKey,
    })
    return EditorState.forceSelection(editorState, updatedSelection)
  }
}

export const moveCaretOutsideEntity = ({
  editorState,
  entity,
  direction = 'right',
}) => {
  const selection = editorState.getSelection()
  const focusKey = selection.getFocusKey()

  const start = selection.getStartOffset()
  const end = selection.getEndOffset()

  if (start === end) {
    const pointOffset = direction === 'left' ? entity.start : entity.end

    const updatedSelection = SelectionState.createEmpty(focusKey).merge({
      anchorOffset: pointOffset,
      focusOffset: pointOffset,
      focusKey,
    })
    const updatedState = EditorState.forceSelection(
      editorState,
      updatedSelection,
    )
    const updatedStateNearZwsp = checkCaretIsNearZwsp({
      editorState: updatedState,
      direction,
    })
    return updatedStateNearZwsp ? updatedStateNearZwsp : updatedState
  }
}

export const isCaretInsideEntity = () => {
  const entityContainer = getEntityContainer('tag-container')
  if (entityContainer) {
    const selection = window.getSelection()
    const textNode = getTextNode(entityContainer)
    const {focusOffset} = selection
    if (focusOffset > 0 && focusOffset < textNode.length) return true
  }

  return false
}

export const adjustCaretPosition = (direction) => {
  const selection = window.getSelection()
  if (!selection) return

  const entityContainer = getEntityContainer('tag-container')

  // remove caret inside entity
  if (entityContainer) {
    const focusOnElement =
      direction === 'left'
        ? entityContainer.previousElementSibling
        : entityContainer.nextElementSibling

    if (focusOnElement) {
      const textNode = getTextNode(focusOnElement)
      console.log('textNode', textNode)
      const offset = direction === 'left' ? textNode.length : 0
      console.log('#offset', offset, direction)

      const anchorNode = getTextNode(selection.anchorNode)
      const {anchorOffset, focusOffset} = selection
      const isSelectingContent = anchorOffset !== focusOffset

      if (isSelectingContent) {
        selection.setBaseAndExtent(anchorNode, anchorOffset, textNode, offset)
      } else {
        const range = document.createRange()
        range.setStart(textNode, offset)
        range.collapse(true)
        selection.removeAllRanges()
        selection.addRange(range)
      }
    } else {
      const textNode = getTextNode(entityContainer)
      const offset = direction === 'left' ? 0 : textNode.length
      const range = document.createRange()
      range.setStart(textNode, offset)
      range.collapse(true)
      selection.removeAllRanges()
      selection.addRange(range)
    }
  }
}
