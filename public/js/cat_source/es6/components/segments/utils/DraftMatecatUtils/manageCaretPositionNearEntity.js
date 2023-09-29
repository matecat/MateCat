import {EditorState, SelectionState} from 'draft-js'
import getEntities from './getEntities'

const ZWSP = String.fromCharCode(parseInt('200B', 16))

const getStepByDirection = (direction) => {
  const isRTL = Boolean(config.isTargetRTL)
  const directionStep = direction === 'left' ? -1 : 1
  const step = isRTL ? directionStep * -1 : directionStep
  return step
}

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

export const checkCaretIsNearZwsp = ({
  editorState,
  direction = 'right',
  isShiftPressed = false,
}) => {
  const selection = editorState.getSelection()
  const contentState = editorState.getCurrentContent()
  const anchorKey = selection.getStartKey()
  const focusKey = selection.getFocusKey()
  const isBackward = direction === 'left'
  const currentBlock = contentState.getBlockForKey(focusKey)

  const start = selection.getFocusOffset()
  const blockedOffset = isShiftPressed && selection.getAnchorOffset()
  const step = getStepByDirection(direction)

  const point = {
    ...(step > 0 ? {start, end: start + 1} : {start: start - 1, end: start}),
  }

  const textPortion = currentBlock.getText().substring(point.start, point.end)
  if (textPortion === ZWSP) {
    const pointOffset = start + step

    const updatedSelection = SelectionState.createEmpty(anchorKey).merge({
      anchorOffset: blockedOffset ? blockedOffset : pointOffset,
      focusOffset: pointOffset,
      focusKey: anchorKey,
      isBackward,
    })
    return EditorState.forceSelection(editorState, updatedSelection)
  }
}

export const checkCaretIsNearEntity = ({
  editorState,
  direction = 'right',
  isShiftPressed = false,
}) => {
  const selection = editorState.getSelection()

  const start = selection.getFocusOffset()
  const end = selection.getEndOffset()
  const step = getStepByDirection(direction)

  const entities = getEntities(editorState)
  const entityMatched = entities.find((entity) =>
    step < 0
      ? start <= entity.end && start > entity.start
      : end < entity.end && end >= entity.start,
  )
  console.log('entityMatched', entityMatched, step)
  return entityMatched
    ? moveCaretOutsideEntity({
        editorState,
        entity: entityMatched,
        direction,
        isShiftPressed,
      })
    : undefined
}

export const moveCaretOutsideEntity = ({
  editorState,
  entity,
  direction = 'right',
  isShiftPressed = false,
}) => {
  const selection = editorState.getSelection()
  const anchorKey = selection.getStartKey()
  const isBackward = direction === 'left'

  const blockedOffset = isShiftPressed && selection.getAnchorOffset()
  const step = getStepByDirection(direction)
  const pointOffset = step < 0 ? entity.start : entity.end

  const updatedSelection = SelectionState.createEmpty(anchorKey).merge({
    anchorOffset: blockedOffset ? blockedOffset : pointOffset,
    focusOffset: pointOffset,
    focusKey: anchorKey,
    isBackward,
  })
  const updatedState = EditorState.forceSelection(editorState, updatedSelection)
  const updatedStateNearZwsp = checkCaretIsNearZwsp({
    editorState: updatedState,
    direction,
    isShiftPressed,
  })
  return updatedStateNearZwsp ? updatedStateNearZwsp : updatedState
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

export const adjustCaretPosition = ({direction, isShiftPressed}) => {
  const selection = window.getSelection()
  if (!selection) return

  const step = getStepByDirection(direction)
  console.log('adjustCaretPosition')

  const entityContainer = getEntityContainer('tag-container')

  // remove caret inside entity
  if (entityContainer) {
    const focusOnElement =
      step < 0
        ? entityContainer.previousElementSibling
        : entityContainer.nextElementSibling

    if (focusOnElement) {
      const textNode = getTextNode(focusOnElement)
      const offset = step < 0 ? textNode.length : 0
      const {anchorOffset, anchorNode} = selection

      if (isShiftPressed) {
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
      const offset = step < 0 ? 0 : textNode.length
      const range = document.createRange()
      range.setStart(textNode, offset)
      range.collapse(true)
      selection.removeAllRanges()
      selection.addRange(range)
    }
  }
}
