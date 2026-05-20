import matchTag from './matchTag'
import {Modifier, SelectionState, ContentState} from 'draft-js'
import {decodeHtmlEntities} from './tagUtils'

/**
 *
 * @param editorState - current editor state, can be empty
 * @param plainText - text where each entity applies
 * @param excludedTagsType - array of tags type. Entity won't be created for these tags.
 * @returns {{ContentState, tagRange}} contentState - The object with the ContentState with each tag mapped as an entity
 * and the array of the mapped tags.
 */
const createNewEntitiesFromMap = (
  editorState,
  excludedTagsType,
  plainText = '',
  sourceTagMap,
) => {
  const excludeReplaceZWSP = []
  // Compute tag range ( all tags are included, also nbsp, tab, CR and LF)
  const tagRange = matchTag(plainText) // absolute offset
  // Apply each entity to the block where it belongs
  let maxCharsInBlocks = 0
  tagRange.sort((a, b) => {
    return a.offset - b.offset
  })

  const offsetWithEntities = []
  let slicedLength = 0

  const shouldCompareWithSourceTagMap = sourceTagMap?.length && tagRange.length

  const tagRangeWithIndexes = shouldCompareWithSourceTagMap
    ? addIncrementalIndex(
        tagRange.map((tag) => {
          // Only match by id when the tag has a non-empty id
          // (XLIFF2 ph tags have empty ids — matching on "" would
          // incorrectly pair all of them with the first source tag)
          const tagSource = tag.data.id
            ? sourceTagMap.find(({data}) => data.id === tag.data.id)
            : undefined
          return {
            ...tag,
            data: {
              ...tag.data,
              ...(tagSource?.data?.index !== undefined && {
                index: tagSource.data.index,
              }),
            },
          }
        }),
      )
    : addIncrementalIndex(tagRange)

  // Executre replace with placeholder and adapt offsets
  tagRangeWithIndexes.forEach((tagEntity) => {
    const {name: tagName, placeholder, encodedText} = tagEntity.data
    if (!excludedTagsType.includes(tagName)) {
      const start = tagEntity.offset - slicedLength
      const end = start + encodedText.length
      offsetWithEntities.push({start, tag: tagEntity})
      if (!excludeReplaceZWSP.includes(tagName)) {
        plainText =
          plainText.slice(0, start) +
          '​' +
          placeholder +
          '​' +
          plainText.slice(end) //String.fromCharCode(parseInt('200B',16))
        slicedLength += end - start - (placeholder.length + 2) // add 2 ZWSP
      } else {
        plainText =
          plainText.slice(0, start) + placeholder + plainText.slice(end)
        slicedLength += end - start - placeholder.length // add 2 ZWSP
      }
    }
  })

  //Find all brackets occurrences
  let brackets = []
  const regex = /&lt;|&gt;/gi
  let result
  while ((result = regex.exec(plainText))) {
    brackets.push({offset: result.index})
  }

  //Removed after change, we do not decode &lt; and &gt;
  /*if (brackets.length > 0) {
    offsetWithEntities.map((tag) => {
      const start = tag.start
      brackets.forEach((bracket) => {
        if (start > bracket.offset) {
          tag.start -= 3 //
        }
      })
      return tag
    })
  }*/
  // New contentState without entities
  let plainContentState = ContentState.createFromText(plainText)
  const blocks = plainContentState.getBlockMap()
  const firstBlockKey = plainContentState.getFirstBlock().getKey()
  blocks.forEach((contentBlock) => {
    const loopedBlockKey = contentBlock.getKey()
    // Add current block length
    const newLineChar = loopedBlockKey !== firstBlockKey ? 1 : 0
    maxCharsInBlocks += contentBlock.getLength() + newLineChar

    offsetWithEntities.forEach((tagEntity) => {
      const {start, tag} = tagEntity
      const extraPositionZWSP = excludeReplaceZWSP.includes(
        tagEntity.tag.data.name,
      )
        ? 0
        : 1
      if (
        start + extraPositionZWSP < maxCharsInBlocks &&
        start + extraPositionZWSP + tag.data.placeholder.length <=
          maxCharsInBlocks &&
        start + extraPositionZWSP >=
          maxCharsInBlocks - contentBlock.getLength() &&
        !excludedTagsType.includes(tag.data.name)
      ) {
        // Clone tag
        const tagEntity = {...tag}
        const blockLength = contentBlock.getLength()
        // Each block start with offset = 0 so we have to adapt selection
        let selectionState = SelectionState.createEmpty(contentBlock.getKey())
        selectionState = selectionState.merge({
          anchorOffset:
            start + extraPositionZWSP - (maxCharsInBlocks - blockLength),
          focusOffset:
            start +
            extraPositionZWSP +
            tag.data.placeholder.length -
            (maxCharsInBlocks - blockLength),
        })
        // Create entity
        const {type, mutability, data} = tagEntity
        const contentStateWithEntity = plainContentState.createEntity(
          type,
          mutability,
          data,
        )
        const entityKey = contentStateWithEntity.getLastCreatedEntityKey()

        // apply entity
        plainContentState = Modifier.applyEntity(
          contentStateWithEntity,
          selectionState,
          entityKey,
        )
      }
    })
  })
  return {
    contentState: plainContentState,
    tagRange: tagRangeWithIndexes,
  }
}

const addIncrementalIndex = (tagRange) => {
  let phIndex = 0
  return tagRange.map((cur) => {
    if (cur.data.name !== 'ph') return cur

    if (cur.data.index !== undefined) {
      phIndex = Math.max(phIndex, cur.data.index + 1)
      return cur
    }

    return {
      ...cur,
      data: {
        ...cur.data,
        index: phIndex++,
      },
    }
  })
}

const isXliff2 = (encodedText) =>
  /\bph\b/.test(encodedText) && !/id=\"mtc_/.test(encodedText) //eslint-disable-line

export default createNewEntitiesFromMap
