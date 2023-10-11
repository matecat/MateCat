import {EditorState, SelectionState} from 'draft-js'
import getEntities from './getEntities'

const entityClassname = 'tag-container'
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
  const step = getStepByDirection(direction)

  const selection = editorState.getSelection()
  const contentState = editorState.getCurrentContent()
  const anchorKey = selection.getAnchorKey()
  const focusKey = selection.getFocusKey()
  const isBackward = step < 0
  const currentBlock = contentState.getBlockForKey(focusKey)

  const start = selection.getFocusOffset()
  const blockedOffset = isShiftPressed && selection.getAnchorOffset()

  const point = {
    ...(step > 0 ? {start, end: start + 1} : {start: start - 1, end: start}),
  }

  const textPortion = currentBlock.getText().substring(point.start, point.end)
  if (textPortion === ZWSP) {
    const pointOffset = start + step

    const updatedSelection = SelectionState.createEmpty(anchorKey).merge({
      anchorOffset: blockedOffset ? blockedOffset : pointOffset,
      focusOffset: pointOffset,
      focusKey,
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
  const focusKey = selection.getFocusKey()

  const start = selection.getFocusOffset()
  const end = selection.getEndOffset()
  const step = getStepByDirection(direction)

  const entities = getEntities(editorState)
  const entityMatched = entities.find((entity) =>
    entity.blockKey === focusKey
      ? step < 0
        ? start <= entity.end && start > entity.start
        : end < entity.end && end >= entity.start
      : false,
  )

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
  const step = getStepByDirection(direction)

  const selection = editorState.getSelection()
  const anchorKey = selection.getAnchorKey()
  const focusKey = selection.getFocusKey()
  const isBackward = step < 0

  const blockedOffset = isShiftPressed && selection.getAnchorOffset()
  const pointOffset = step < 0 ? entity.start : entity.end

  const updatedSelection = SelectionState.createEmpty(anchorKey).merge({
    anchorOffset: blockedOffset ? blockedOffset : pointOffset,
    focusOffset: pointOffset,
    focusKey,
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
  const entityContainer = getEntityContainer(entityClassname)
  if (entityContainer) {
    const selection = window.getSelection()
    const textNode = getTextNode(entityContainer)
    const {focusOffset} = selection
    if (focusOffset >= 0 && focusOffset <= textNode.length) return true
  }

  return false
}

export const adjustCaretPosition = ({direction, isShiftPressed}) => {
  const selection = window.getSelection()
  if (!selection) return

  const entityContainer = getEntityContainer(entityClassname)

  // remove caret inside entity
  if (entityContainer) {
    // avoid caret adjustment when cursor move forward and previous element is an entity
    if (
      direction === 'right' &&
      entityContainer.previousElementSibling?.classList?.contains(
        entityClassname,
      )
    )
      return

    const focusOnElement =
      direction === 'left'
        ? entityContainer.previousElementSibling
        : entityContainer.nextElementSibling

    if (focusOnElement) {
      const textNode = getTextNode(focusOnElement)
      const offset = direction === 'left' ? textNode.length : 0
      const {anchorOffset, anchorNode} = selection

      if (isShiftPressed) {
        selection.setBaseAndExtent(anchorNode, anchorOffset, textNode, offset)
      } else {
        const charAtOffset =
          direction === 'left'
            ? textNode.textContent.slice(textNode.length - 1)
            : textNode.textContent[0]
        const isOffsetNearZwsp = charAtOffset === ZWSP

        const range = document.createRange()
        range.setStart(
          textNode,
          isOffsetNearZwsp
            ? direction === 'left'
              ? offset - 1
              : offset + 1
            : offset,
        )
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
