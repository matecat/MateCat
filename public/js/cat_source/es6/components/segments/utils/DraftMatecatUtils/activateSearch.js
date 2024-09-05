import {cloneDeep} from 'lodash'
import SearchHighlight from '../../SearchHighLight/SearchHighLight.component'
import * as DraftMatecatConstants from './editorConstants'
import SearchUtils from '../../../header/cattol/search/searchUtils'

const activateSearch = (
  text,
  params,
  occurrencesInSegment,
  currentIndex,
  tagRange,
) => {
  const generateSearchDecorator = (
    highlightTerm,
    occurrences,
    params,
    currentIndex,
    tagRange,
  ) => {
    let regex = SearchUtils.getSearchRegExp(
      highlightTerm,
      params.ingnoreCase,
      params.exactMatch,
      true,
    )
    return {
      name: DraftMatecatConstants.SEARCH_DECORATOR,
      strategy: (contentBlock, callback) => {
        if (highlightTerm !== '') {
          findWithRegex(regex, contentBlock, occurrences, tagRange, callback)
        }
      },
      component: SearchHighlight,
      props: {
        occurrences,
        currentIndex,
        tagRange,
      },
    }
  }

  const findWithRegex = (
    regex,
    contentBlock,
    occurrences,
    tagRange,
    callback,
  ) => {
    const text = contentBlock.getText()
    const key = contentBlock.getKey()
    let matchArr, start, end
    let index = 0
    while ((matchArr = regex.exec(text)) !== null) {
      start = matchArr.index
      end = start + matchArr[0].length
      if (occurrences[index]) {
        if (!occurrences[index].start || occurrences[index].key === key) {
          occurrences[index].start = start
          occurrences[index].end = end
          occurrences[index].key = key
        } else if (occurrences[index + 1]) {
          occurrences[index + 1].start = start
          occurrences[index].end = end
          occurrences[index + 1].key = key
          // index++
        }
      }
      //!isTag(start, tagRange) && callback(start, end)
      handleTagInside(start, end, contentBlock, callback)
      index++
    }
  }

  const hasEntity = (charPosition, contentBlock) => {
    return contentBlock.getEntityAt(charPosition)
  }

  const handleTagInside = (start, end, contentBlock, callback) => {
    let cursor = start
    while (cursor < end) {
      // start
      while (hasEntity(cursor, contentBlock) && cursor < end) {
        cursor++
      }
      let tempStart = cursor
      // end
      while (!hasEntity(cursor, contentBlock) && cursor < end) {
        cursor++
      }
      // no entity between, end loop
      if (cursor === tempStart) {
        cursor = end
      }
      let tempEnd = cursor
      callback(tempStart, tempEnd)
    }
  }

  let search = text
  let occurrencesClone = cloneDeep(occurrencesInSegment)
  return generateSearchDecorator(
    search,
    occurrencesClone,
    params,
    currentIndex,
    tagRange,
  )
}

export default activateSearch
