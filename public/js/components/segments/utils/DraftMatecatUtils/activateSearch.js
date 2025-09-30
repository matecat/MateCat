import {cloneDeep} from 'lodash'
import SearchHighlight from '../../SearchHighLight/SearchHighLight.component'
import * as DraftMatecatConstants from './editorConstants'
import SearchUtils from '../../../header/cattol/search/searchUtils'
import TEXT_UTILS from '../../../../utils/textUtils'

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
    let lastElement = occurrences.findIndex((item) => item.key === key)
    let index = lastElement > -1 ? lastElement : 0
    while ((matchArr = regex.exec(text)) !== null) {
      start = matchArr.index
      end = start + matchArr[0].length
      if (occurrences[index]) {
        if (occurrences[index].key !== key) {
          if (!occurrences[index].start) {
            occurrences[index].start = start
            occurrences[index].end = end
            occurrences[index].key = key
          } else {
            let elementToUpdate = occurrences.findIndex((item) => !item.key)
            occurrences[elementToUpdate].start = start
            occurrences[elementToUpdate].end = end
            occurrences[elementToUpdate].key = key
          }
        }
        index++
      }
      //!isTag(start, tagRange) && callback(start, end)
      TEXT_UTILS.handleTagInside(start, end, contentBlock, callback)
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
