import {isUndefined, clone} from 'lodash'
import {find} from 'lodash/collection'
import {findIndex} from 'lodash/array'

import SegmentActions from '../../../../actions/SegmentActions'
import CatToolActions from '../../../../actions/CatToolActions'
import SegmentStore from '../../../../stores/SegmentStore'
import TextUtils from '../../../../utils/textUtils'
import {searchTermIntoSegments} from '../../../../api/searchTermIntoSegments'
import {replaceAllIntoSegments} from '../../../../api/replaceAllIntoSegments'
import AlertModal from '../../../modals/AlertModal'
import ModalsActions from '../../../../actions/ModalsActions'
import {tagSignatures} from '../../../segments/utils/DraftMatecatUtils/tagModel'
import {
  REVISE_STEP_NUMBER,
  SEGMENTS_STATUS,
} from '../../../../constants/Constants'

let SearchUtils = {
  searchEnabled: true,
  searchParams: {
    search: 0,
  },
  total: 0,
  searchResults: [],
  occurrencesList: [],
  searchResultsDictionary: {},
  featuredSearchResult: 0,
  searchSegmentsResult: [],

  /**
   * Called by the search component to execute search
   * @returns {boolean}
   */
  execFind: function (params) {
    $('section.currSearchSegment').removeClass('currSearchSegment')

    let nbspRegexp = new RegExp(tagSignatures.nbsp.placeholder, 'g')
    let searchSource = params.searchSource.replace(nbspRegexp, ' ')
    if (searchSource !== '' && searchSource !== ' ') {
      this.searchParams.source = searchSource
    } else {
      delete this.searchParams.source
    }
    let searchTarget = params.searchTarget.replace(nbspRegexp, ' ')
    if (searchTarget !== '' && searchTarget !== ' ') {
      this.searchParams.target = searchTarget
    } else {
      delete this.searchParams.target
    }

    let selectStatus = params.selectStatus
    if (selectStatus !== '') {
      selectStatus =
        selectStatus.toUpperCase() === SEGMENTS_STATUS.APPROVED &&
        params.revisionNumber === REVISE_STEP_NUMBER.REVISE2
          ? SEGMENTS_STATUS.APPROVED2
          : selectStatus
      this.searchParams.status = selectStatus
      this.searchParams.status = this.searchParams.status.toLowerCase()
    } else {
      delete this.searchParams.status
    }

    let replaceTarget = params.replaceTarget
    if (replaceTarget !== '') {
      this.searchParams.replace = replaceTarget
    } else {
      delete this.searchParams.replace
    }
    this.searchParams['match-case'] = params.matchCase
    this.searchParams['exact-match'] = params.exactMatch
    this.searchParams['strict_mode'] = !params.entireJob

    if (
      isUndefined(this.searchParams.source) &&
      isUndefined(this.searchParams.target) &&
      this.searchParams.status == 'all'
    ) {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'Enter text in source or target input boxes or select a status.',
        },
        'Search Alert',
      )
      return false
    }

    let p = this.searchParams

    this.searchMode =
      !isUndefined(p.source) && !isUndefined(p.target)
        ? 'source&target'
        : 'normal'

    this.searchParams.searchMode = this.searchMode

    let source = p.source ? p.source : ''
    let target = p.target ? p.target : ''
    let replace = p.replace ? p.replace : ''

    UI.body.addClass('searchActive')
    let makeSearchFn = () => {
      let dd = new Date()

      searchTermIntoSegments({
        token: dd.getTime(),
        source: source,
        target: target,
        status: this.searchParams.status,
        matchcase: this.searchParams['match-case'],
        exactmatch: this.searchParams['exact-match'],
        strictMode: this.searchParams['strict_mode'],
        revisionNumber: params.revisionNumber,
        replace,
      }).then((data) => {
        SearchUtils.execFind_success(data)
      })
    }
    //Save the current segment to not lose the translation
    try {
      let segment = SegmentStore.getSegmentByIdToJS(UI.currentSegmentId)
      if (UI.translationIsToSaveBeforeClose(segment)) {
        UI.saveSegment(UI.currentSegment).then(() => {
          makeSearchFn()
        })
      } else {
        makeSearchFn()
      }
    } catch (e) {
      makeSearchFn()
    }
  },
  /**
   * Call in response to request getSearch
   * @param response
   */
  execFind_success: function (response) {
    this.resetSearch()
    this.searchSegmentsResult = response.segments
    if (response.segments.length > 0) {
      let searchObject = this.createSearchObject(response.segments)

      this.occurrencesList = clone(searchObject.occurrencesList)
      this.searchResultsDictionary = clone(searchObject.searchResultsDictionary)

      this.searchParams.current = searchObject.occurrencesList[0]

      CatToolActions.storeSearchResults({
        total: response.total,
        searchResults: searchObject.searchResults,
        occurrencesList: this.occurrencesList,
        searchResultsDictionary: clone(this.searchResultsDictionary),
        featuredSearchResult: 0,
      })
      SegmentActions.addSearchResultToSegments(
        this.occurrencesList,
        this.searchResultsDictionary,
        0,
        searchObject.searchParams,
      )
    } else {
      SegmentActions.removeSearchResultToSegments()
      this.resetSearch()
      CatToolActions.storeSearchResults({
        total: 0,
        searchResults: [],
        occurrencesList: [],
        searchResultsDictionary: {},
        featuredSearchResult: 0,
      })
    }
  },

  updateSearchObjectAfterReplace: function (segmentsResult) {
    this.searchSegmentsResult = segmentsResult
      ? segmentsResult
      : this.searchSegmentsResult
    let searchObject = this.createSearchObject(this.searchSegmentsResult)
    this.occurrencesList = searchObject.occurrencesList
    this.searchResultsDictionary = searchObject.searchResultsDictionary
    return searchObject
  },

  updateSearchObject: function () {
    let currentFeaturedSegment = this.occurrencesList[this.featuredSearchResult]
    let searchObject = this.createSearchObject(this.searchSegmentsResult)
    this.occurrencesList = searchObject.occurrencesList
    this.searchResultsDictionary = searchObject.searchResultsDictionary
    let newIndex = findIndex(
      this.occurrencesList,
      (item) => item === currentFeaturedSegment,
    )
    if (newIndex > -1) {
      this.featuredSearchResult = newIndex
    } else {
      this.featuredSearchResult = this.featuredSearchResult + 1
    }
    searchObject.featuredSearchResult = this.featuredSearchResult
    return searchObject
  },

  getSearchRegExp(textToMatch, ignoreCase, isExactMatch) {
    let ignoreFlag = ignoreCase ? '' : 'i'
    textToMatch = TextUtils.escapeRegExp(textToMatch)
    let reg = new RegExp('(' + textToMatch + ')', 'g' + ignoreFlag)
    if (isExactMatch) {
      reg = new RegExp('\\b(' + textToMatch + ')\\b', 'g' + ignoreFlag)
    }
    return reg
  },

  getMatchesInText: function (text, textToMatch, ignoreCase, isExactMatch) {
    let reg = this.getSearchRegExp(textToMatch, ignoreCase, isExactMatch)
    return text.matchAll(reg)
  },

  createSearchObject: function (segments) {
    let searchProgressiveIndex = 0
    let occurrencesList = [],
      searchResultsDictionary = {}
    let searchParams = {}
    searchParams.source = this.searchParams.source
      ? this.searchParams.source.replace(/ /g, tagSignatures.nbsp.placeholder)
      : null

    searchParams.target = this.searchParams.target
      ? this.searchParams.target.replace(/ /g, tagSignatures.nbsp.placeholder)
      : null
    searchParams.ingnoreCase = !!this.searchParams['match-case']
    searchParams.exactMatch = this.searchParams['exact-match']
    let searchResults = segments.map((sid) => {
      let segment = SegmentStore.getSegmentByIdToJS(sid)
      let item = {id: sid, occurrences: []}
      if (segment) {
        if (this.searchParams.searchMode === 'source&target') {
          let textSource = segment.decodedSource
          const matchesSource = this.getMatchesInText(
            textSource,
            searchParams.source,
            searchParams.ingnoreCase,
            this.searchParams['exact-match'],
          )
          let textTarget = segment.decodedTranslation
          const matchesTarget = this.getMatchesInText(
            textTarget,
            searchParams.target,
            searchParams.ingnoreCase,
            this.searchParams['exact-match'],
          )

          let sourcesMatches = [],
            targetMatches = []
          for (const match of matchesSource) {
            sourcesMatches.push(match)
          }
          for (const match of matchesTarget) {
            targetMatches.push(match)
          }
          //Check if source and target has the same occurrences
          let matches =
            sourcesMatches.length > targetMatches.length
              ? sourcesMatches
              : targetMatches
          for (const match of matches) {
            occurrencesList.push(sid)
            item.occurrences.push({
              matchPosition: match.index,
              searchProgressiveIndex: searchProgressiveIndex,
            })
            searchProgressiveIndex++
          }
        } else {
          if (this.searchParams.source) {
            let text = segment.decodedSource
            const matchesSource = this.getMatchesInText(
              text,
              searchParams.source,
              searchParams.ingnoreCase,
              this.searchParams['exact-match'],
            )
            for (const match of matchesSource) {
              occurrencesList.push(sid)
              item.occurrences.push({
                matchPosition: match.index,
                searchProgressiveIndex: searchProgressiveIndex,
              })
              searchProgressiveIndex++
            }
          } else if (this.searchParams.target) {
            let text = segment.decodedTranslation
            const matchesTarget = this.getMatchesInText(
              text,
              searchParams.target,
              searchParams.ingnoreCase,
              this.searchParams['exact-match'],
            )
            for (const match of matchesTarget) {
              occurrencesList.push(sid)
              item.occurrences.push({
                matchPosition: match.index,
                searchProgressiveIndex: searchProgressiveIndex,
              })
              searchProgressiveIndex++
            }
          }
        }
      } else {
        searchProgressiveIndex++
        occurrencesList.push(sid)
      }
      searchResultsDictionary[sid] = item
      return item
    })
    // console.log("SearchResults", searchResults);
    // console.log("occurrencesList", occurrencesList);
    // console.log("searchResultsDictionary", searchResultsDictionary);
    return {
      searchParams: searchParams,
      searchResults: searchResults,
      occurrencesList: occurrencesList,
      searchResultsDictionary: searchResultsDictionary,
    }
  },

  /**
   * Toggle the Search container
   * @param e
   */
  toggleSearch: function (e) {
    if (!this.searchEnabled) return
    if (UI.body.hasClass('searchActive')) {
      CatToolActions.closeSearch()
    } else {
      e.preventDefault()
      CatToolActions.toggleSearch()
      // this.fixHeaderHeightChange();
    }
  },
  /**
   * Executes the replace all for segments if all the params are ok
   * @returns {boolean}
   */
  execReplaceAll: function (params) {
    // $('.search-display .numbers').text('No segments found');
    // this.applySearch();

    let searchSource = params.searchSource
    if (
      searchSource !== '' &&
      searchSource !== ' ' &&
      searchSource !== "'" &&
      searchSource !== '"'
    ) {
      this.searchParams.source = searchSource
    } else {
      delete this.searchParams.source
    }

    let searchTarget = params.searchTarget
    if (searchTarget !== '' && searchTarget !== ' ') {
      this.searchParams.target = searchTarget
    } else {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'You must specify the Target value to replace.',
        },
        'Search Alert',
      )
      delete this.searchParams.target
      return false
    }

    let replaceTarget = params.replaceTarget
    if (replaceTarget !== '') {
      this.searchParams.replace = replaceTarget
    } else {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'You must specify the replacement value.',
        },
        'Search Alert',
      )
      delete this.searchParams.replace
      return false
    }

    if (params.selectStatus !== '' && params.selectStatus !== 'all') {
      this.searchParams.status = params.selectStatus
      UI.body.attr('data-filter-status', params.selectStatus)
    } else {
      delete this.searchParams.status
    }

    this.searchParams['match-case'] = params.matchCase
    this.searchParams['exact-match'] = params.exactMatch

    let p = this.searchParams
    let source = p.source ? TextUtils.htmlEncode(p.source) : ''
    let target = p.target ? TextUtils.htmlEncode(p.target) : ''
    let replace = p.replace ? p.replace : ''
    let dd = new Date()

    return replaceAllIntoSegments({
      token: dd.getTime(),
      source,
      target,
      status: p.status,
      matchcase: p['match-case'],
      exactmatch: p['exact-match'],
      replace,
    })
  },

  updateFeaturedResult(value) {
    this.featuredSearchResult = value
  },

  prepareTextToReplace: (text) => {
    const getIndex = (regExp, source) => {
      const matchedIndex = []
      let result
      while ((result = regExp.exec(source))) {
        const {index} = result
        matchedIndex.push(index)
      }
      return matchedIndex
    }

    const LTPLACEHOLDER = '##LESSTHAN##'
    const GTPLACEHOLDER = '##GREATERTHAN##'

    const cleaned = text
      .replace(/</g, LTPLACEHOLDER)
      .replace(/>/g, GTPLACEHOLDER)
    const LTP_REGEX = new RegExp(LTPLACEHOLDER, 'g')
    const GTP_REGEX = new RegExp(GTPLACEHOLDER, 'g')

    const indexes = [
      ...getIndex(LTP_REGEX, cleaned).map((value, index) => ({
        start: value,
        index,
      })),
      ...getIndex(GTP_REGEX, cleaned).map((value, index) => ({
        end: value,
        index,
      })),
    ].flatMap((item, index, arr) =>
      item.end ? {start: arr[item.index].start, end: item.end} : [],
    )

    return {
      text: cleaned,
      tagsIntervals: indexes,
    }
  },

  restoreTextAfterReplace(text) {
    // text = text.replace(/(\$&)/g, function ( match, text ) {
    //     return tagsArray.shift();
    // });
    //text = text.replace(/>/g, '&gt;').replace(/</g, '&lt;')
    text = text.replace(/##GREATERTHAN##/g, '>').replace(/##LESSTHAN##/g, '<')
    return text
  },

  markText: function (textToMark, isSource, sid) {
    if (this.occurrencesList.indexOf(sid) === -1) return textToMark
    let reg
    const isCurrent = this.occurrencesList[this.featuredSearchResult] === sid
    const occurrences = this.searchResultsDictionary[sid].occurrences
    let params = this.searchParams

    let searchMarker = 'searchMarker'
    let ignoreCase = params['match-case'] ? '' : 'i'
    if (this.searchMode === 'source&target') {
      let txt = isSource ? params.source : params.target
      txt = TextUtils.escapeRegExp(txt)
      reg = new RegExp('(' + txt + ')', 'g' + ignoreCase)
    } else if (
      (!isUndefined(params.source) && isSource) ||
      (!isUndefined(params.target) && !isSource)
    ) {
      let txt = params.source ? params.source : params.target
      txt = txt
        .replace(/&/g, '&amp;')
        .replace(/</gi, '&lt;')
        .replace(/>/gi, '&gt;')
      let regTxt = TextUtils.escapeRegExp(txt)
      // regTxt = regTxt.replace(/\(/gi, "\\(").replace(/\)/gi, "\\)");

      reg = new RegExp('(' + regTxt + ')', 'g' + ignoreCase)

      if (params['exact-match']) {
        reg = new RegExp('\\b(' + regTxt + ')\\b', 'g' + ignoreCase)
      }

      // Finding double spaces
      if (txt === '  ') {
        reg = new RegExp(/(&nbsp; )/, 'gi')
      }
    }

    let {text, tagsIntervals} = this.prepareTextToReplace(textToMark)

    let matchIndex = 0
    text = text.replace(reg, (match, text, index) => {
      let intervalSpan = find(
        tagsIntervals,
        (item) => index > item.start && index < item.end,
      )
      if (!intervalSpan) {
        let className =
          isCurrent &&
          occurrences[matchIndex] &&
          occurrences[matchIndex].searchProgressiveIndex ===
            this.featuredSearchResult
            ? searchMarker + ' currSearchItem'
            : searchMarker
        matchIndex++
        return (
          '##LESSTHAN##mark class="' +
          className +
          '"##GREATERTHAN##' +
          match +
          '##LESSTHAN##/mark##GREATERTHAN##'
        )
      } else {
        return match
      }
    })
    text = this.restoreTextAfterReplace(text)
    return text
  },
  resetSearch: function () {
    this.searchResults = []
    this.occurrencesList = []
    this.searchResultsDictionary = {}
    this.featuredSearchResult = 0
    this.searchSegmentsResult = []
    SegmentActions.removeSearchResultToSegments()
  },
  /**
   * Close search container
   */
  closeSearch: function () {
    this.resetSearch()
    CatToolActions.closeSubHeader()
    SegmentActions.removeSearchResultToSegments()

    CatToolActions.storeSearchResults({
      total: 0,
      searchResults: [],
      occurrencesList: [],
      searchResultsDictionary: {},
      featuredSearchResult: 0,
    })
  },
}

export default SearchUtils
