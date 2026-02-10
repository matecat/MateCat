export const getSelectedTextWithTags = (editorState) => {
  const selectionState = editorState.getSelection()
  const anchorKey = selectionState.getAnchorKey()
  const currentContent = editorState.getCurrentContent()
  const currentContentBlock = currentContent.getBlockForKey(anchorKey)
  const start = selectionState.getStartOffset()
  const end = selectionState.getEndOffset()

  let result = []

  try {
    result = new Array(end - start)
      .fill({})
      .map((item, index) => start + index)
      .reduce((acc, cur) => {
        const entityKey = currentContentBlock.getEntityAt(cur)
        const isEntity = !!entityKey
        const updateAccumulator = [...acc]

        let entityIndexes = {}

        if (isEntity) {
          currentContentBlock.findEntityRanges(
            (character) => {
              return character.getEntity() === entityKey
            },
            (start, end) => {
              entityIndexes = {start, end}
            },
          )
        }

        if (cur > entityIndexes.start && cur <= entityIndexes.end)
          return updateAccumulator

        const lastItem =
          updateAccumulator.length &&
          (!isEntity || (isEntity && !updateAccumulator.slice(-1)[0]?.value))
            ? updateAccumulator.pop()
            : {}

        const item = {
          ...lastItem,
          ...(isEntity
            ? {
                ...(lastItem.start === undefined && {start: cur}),
                value: currentContent.getEntity(entityKey).getData()
                  .encodedText,
                end: cur + 1,
              }
            : {
                ...(lastItem.start === undefined && {start: cur}),
                value: `${lastItem.value ? lastItem.value : ''}${currentContentBlock
                  .getText()
                  .substring(cur, cur + 1)}`,
                end: cur + 1,
              }),
        }
        return [...updateAccumulator, item]
      }, [])
      .filter((item) => item.value)

    return result
  } catch (e) {
    console.log(e)
  }
  return result
}
