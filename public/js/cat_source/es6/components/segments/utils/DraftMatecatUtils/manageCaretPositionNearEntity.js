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

export const checkCaretIsNearEntity = ({editorState, direction = 'right'}) => {
  const selection = editorState.getSelection()

  const start = selection.getStartOffset()
  const end = selection.getEndOffset()

  const entities = getEntities(editorState)
  const entitiesMatched = entities.find((entity) =>
    direction === 'left'
      ? start <= entity.end && start > entity.start
      : end < entity.end && end >= entity.start,
  )

  return typeof entitiesMatched !== 'undefined'
}

export const isCaretInsideEntity = () =>
  typeof getEntityContainer('tag-container') !== 'undefined'

export const adjustCaretPosition = (direction) => {
  const selection = window.getSelection()
  if (!selection) return

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
