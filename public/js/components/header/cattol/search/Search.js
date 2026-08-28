import React, {useCallback, useEffect, useRef, useState} from 'react'
import $ from 'jquery'
import {isUndefined} from 'lodash'
import {cloneDeep} from 'lodash/lang'
import {find, map} from 'lodash/collection'
import {findIndex, remove} from 'lodash/array'

import CattolConstants from '../../../../constants/CatToolConstants'
import SegmentStore from '../../../../stores/SegmentStore'
import CatToolStore from '../../../../stores/CatToolStore'
import SearchUtils from './searchUtils'
import SegmentConstants from '../../../../constants/SegmentConstants'
import SegmentActions from '../../../../actions/SegmentActions'
import CatToolActions from '../../../../actions/CatToolActions'
import AlertModal from '../../../modals/AlertModal'
import ModalsActions from '../../../../actions/ModalsActions'
import {tagSignatures} from '../../../segments/utils/DraftMatecatUtils/tagModel'
import CommonUtils from '../../../../utils/commonUtils'
import {REVISE_STEP_NUMBER} from '../../../../constants/Constants'
import {Select} from '../../../common/Select'
import {segmentTranslation} from '../../../../setTranslationUtil'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../../common/Button/Button'
import ChevronLeft from '../../../../../img/icons/ChevronLeft'
import ChevronRight from '../../../../../img/icons/ChevronRight'
import {MODAL_KEY} from '../../../../constants/ModalKeys'

// mirrors the class's `defaultState.search` object, used both as the initial
// value and as the value restored by handleCancelClick/handleClearClick
const defaultSearch = {
  enableReplace: false,
  matchCase: false,
  exactMatch: false,
  entireJob: false,
  replaceTarget: '',
  selectStatus: 'all',
  searchTarget: '',
  searchSource: '',
  previousIsTagProjectionEnabled: false,
  isSelectedTag: false,
}

// handling module
const mod = (n, m) => ((n % m) + m) % m

const Search = (props) => {
  const [search, setSearch] = useState(() => cloneDeep(defaultSearch))
  const [focus, setFocus] = useState(true)
  const [funcFindButton, setFuncFindButton] = useState(true) // true=find / false=next
  const [total, setTotal] = useState(null)
  const [searchReturn, setSearchReturn] = useState(false)
  const [searchResults, setSearchResults] = useState([])
  const [occurrencesList, setOccurrencesList] = useState([])
  const [searchResultsDictionary, setSearchResultsDictionary] = useState({})
  const [featuredSearchResult, setFeaturedSearchResult] = useState(null)
  const [previousIsTagProjectionEnabled, setPreviousIsTagProjectionEnabled] =
    useState(false)

  // dead state, preserved verbatim from the class component: `this.state.isReview`
  // is never read anywhere
  // eslint-disable-next-line no-unused-vars
  const [isReview] = useState(props.isReview)
  // dead state, preserved verbatim from the class component: `this.state.searchable_statuses`
  // is never read anywhere (render() uses the global `config.searchable_statuses` instead)
  // eslint-disable-next-line no-unused-vars
  const [searchableStatuses] = useState(props.searchable_statuses)
  const [showReplaceOptionsInSearch] = useState(true)

  const sourceElRef = useRef(null)
  const matchCaseCheckRef = useRef(null)
  const sourceInputRef = useRef(null)
  const targetInputRef = useRef(null)

  // mirrors the class's non-reactive instance field: mutating this does not
  // itself trigger a re-render
  const jobIsSplittedRef = useRef(false)

  const prevActiveRef = useRef(props.active)
  const isFirstRender = useRef(true)

  const latestRef = useRef({})

  const resetSearchState = useCallback(() => {
    setSearch(cloneDeep(defaultSearch))
    setFocus(true)
    setFuncFindButton(true)
    setTotal(null)
    setSearchReturn(false)
    setSearchResults([])
    setOccurrencesList([])
    setSearchResultsDictionary({})
    setFeaturedSearchResult(null)
  }, [])

  const handleStatusChange = (value) => {
    const nextSearch = cloneDeep(search)
    nextSearch.selectStatus = value
    if (value === 'APPROVED-2') {
      nextSearch.revisionNumber = REVISE_STEP_NUMBER.REVISE2
      nextSearch.selectStatus = 'APPROVED'
    } else if (value === 'APPROVED') {
      nextSearch.revisionNumber = REVISE_STEP_NUMBER.REVISE1
    }
    setSearch(nextSearch)
    setFuncFindButton(true)
  }

  const resetStatusFilter = () => {
    handleStatusChange('all')
  }

  const handleClearClick = () => {
    // SearchUtils.clearSearchMarkers();
    resetStatusFilter()
    setTimeout(() => {
      resetSearchState()
      SegmentActions.removeSearchResultToSegments()
    })
  }

  const handleCancelClick = useCallback(() => {
    SearchUtils.searchOpen = false
    latestRef.current.handleClearClick()
    if (SegmentStore.getSegmentByIdToJS(SegmentStore.getCurrentSegmentId())) {
      setTimeout(() =>
        SegmentActions.scrollToSegment(SegmentStore.getCurrentSegmentId()),
      )
    } else {
      CatToolActions.onRender({
        firstLoad: false,
        segmentToOpen: SegmentStore.getCurrentSegmentId(),
      })
    }

    latestRef.current.resetStatusFilter()
    setTimeout(() => {
      CatToolActions.closeSubHeader()
      SegmentActions.removeSearchResultToSegments()
      resetSearchState()
    })
  }, [resetSearchState])

  const setFeatured = (value) => {
    let nextValue = value
    if (occurrencesList.length > 1) {
      nextValue = mod(value, occurrencesList.length)
    } else {
      nextValue = 0
    }
    SearchUtils.updateFeaturedResult(nextValue)
    CatToolActions.storeSearchResults({
      total: total,
      searchResults: searchResults,
      occurrencesList: occurrencesList,
      searchResultsDictionary: searchResultsDictionary,
      featuredSearchResult: nextValue,
    })
    SegmentActions.changeCurrentSearchSegment(nextValue)
  }

  const goToNext = () => {
    setFeatured(featuredSearchResult + 1)
  }

  const goToPrev = () => {
    setFeatured(featuredSearchResult - 1)
  }

  const updateAfterReplace = (sid) => {
    let itemReplaced = find(searchResults, (item) => item.id === sid)
    let newTotal = total
    newTotal--
    if (itemReplaced.occurrences.length === 1) {
      remove(searchResults, (item) => item.id === sid)
    }
    let newResultArray = map(searchResults, (item) => item.id)
    const searchObject =
      SearchUtils.updateSearchObjectAfterReplace(newResultArray)
    setTotal(newTotal)
    setSearchResults(searchObject.searchResults)
    setOccurrencesList(searchObject.occurrencesList)
    setSearchResultsDictionary(searchObject.searchResultsDictionary)
    CatToolActions.storeSearchResults({
      total: newTotal,
      searchResults: searchObject.searchResults,
      occurrencesList: searchObject.occurrencesList,
      searchResultsDictionary: searchObject.searchResultsDictionary,
      featuredSearchResult: featuredSearchResult,
    })
    SegmentActions.addSearchResultToSegments(
      searchObject.occurrencesList,
      searchObject.searchResultsDictionary,
      featuredSearchResult,
      searchObject.searchParams,
    )
  }

  const handleReplaceClick = () => {
    if (search.searchTarget === search.replaceTarget) {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'Attention: you are replacing the same text!',
        },
        'Replace alert',
      )
      return false
    }

    SegmentActions.replaceCurrentSearch(search.replaceTarget)

    setTimeout(() => {
      const segment = SegmentStore.getSegmentByIdToJS(
        occurrencesList[featuredSearchResult],
      )
      if (segment) {
        updateAfterReplace(segment.original_sid)
        segmentTranslation(segment, segment.status, () => {}, false)
      }
    })
  }

  const handleReplaceAllClick = (event) => {
    event.preventDefault()
    let modalProps = {
      search: search,
    }
    ModalsActions.showModalComponent(
      MODAL_KEY.REPLACE_ALL,
      modalProps,
      'Replace text in all results',
    )
  }

  const handleSubmit = () => {
    if (funcFindButton) {
      SearchUtils.execFind(search)
    }

    const {guess_tag: guessTag} = props.userInfo.metadata

    setFuncFindButton(false)
    if (guessTag === 1) {
      setPreviousIsTagProjectionEnabled(true)
    }
    // disable tag projection
    if (guessTag === 1) {
      SegmentActions.changeTagProjectionStatus(false)
    }
  }

  const handleInputChange = (name, event) => {
    //serch model
    const target = event.target
    const value = target.type === 'checkbox' ? target.checked : target.value
    const nextSearch = {...search, [name]: value}

    // `enableReplace` and `replaceTarget` describe the replacement, not the
    // query: neither changes which segments match, so editing them must not
    // throw the current results away. Doing so re-armed FIND, which disabled
    // REPLACE the moment you typed the replacement for the word you had just
    // searched.
    const changesTheQuery = name !== 'enableReplace' && name !== 'replaceTarget'

    if (changesTheQuery) {
      setSearch(nextSearch)
      setFuncFindButton(true)
      setTotal(null)
      setSearchReturn(false)
      setSearchResults([])
      setOccurrencesList([])
      setSearchResultsDictionary({})
      setFeaturedSearchResult(null)
    } else {
      setSearch(nextSearch)
    }
  }

  // dead code, preserved verbatim from the class component: never wired to
  // any JSX handler there either
  // eslint-disable-next-line no-unused-vars
  const replaceTargetOnFocus = () => {
    setSearch({...search, enableReplace: true})
  }

  const handleKeyDown = (e, name) => {
    if (e.code == 'Space' && e.ctrlKey && e.shiftKey) {
      let textToInsert = tagSignatures.nbsp.placeholder
      let cursorPosition = e.target.selectionStart
      let textBeforeCursorPosition = e.target.value.substring(0, cursorPosition)
      let textAfterCursorPosition = e.target.value.substring(
        cursorPosition,
        e.target.value.length,
      )
      e.target.value =
        textBeforeCursorPosition + textToInsert + textAfterCursorPosition
      handleInputChange(name, e)
    }
  }

  const setResults = useCallback((data) => {
    setTotal(data.total)
    setSearchResults(data.searchResults)
    setOccurrencesList(data.occurrencesList)
    setSearchResultsDictionary(data.searchResultsDictionary)
    setFeaturedSearchResult(data.featuredSearchResult)
    setSearchReturn(true)
    setTimeout(() => {
      !isUndefined(data.occurrencesList[data.featuredSearchResult]) &&
        SegmentActions.openSegment(
          data.occurrencesList[data.featuredSearchResult],
        )
    })
  }, [])

  const updateSearch = useCallback(() => {
    if (latestRef.current.active) {
      setTimeout(() => {
        const searchObject = SearchUtils.updateSearchObject()
        setSearchResults(searchObject.searchResults)
        setOccurrencesList(searchObject.occurrencesList)
        setSearchResultsDictionary(searchObject.searchResultsDictionary)
        setFeaturedSearchResult(searchObject.featuredSearchResult)
        setTimeout(() =>
          SegmentActions.addSearchResultToSegments(
            searchObject.occurrencesList,
            searchObject.searchResultsDictionary,
            searchObject.featuredSearchResult,
            searchObject.searchParams,
          ),
        )
      })
    }
  }, [])

  const handelKeydownFunction = useCallback((event) => {
    const {
      active,
      search,
      handleCancelClick,
      handleSubmit,
      goToPrev,
      goToNext,
    } = latestRef.current
    if (active) {
      if (event.keyCode === 27) {
        handleCancelClick()
      } else if (
        event.keyCode === 13 &&
        $(event.target).closest('.find-container').length > 0
      ) {
        if (search.searchTarget !== '' || search.searchSource !== '') {
          event.preventDefault()
          handleSubmit()
        }
      } else if (event.key === 'F3' && event.shiftKey) {
        event.preventDefault()
        goToPrev()
      } else if (event.key === 'F3') {
        event.preventDefault()
        goToNext()
      }
    }
  }, [])

  const getResultsHtml = () => {
    var html = ''
    const segmentIndex = findIndex(
      searchResults,
      (item) => item.id === occurrencesList[featuredSearchResult],
    )
    //Waiting for results
    if (!funcFindButton && !searchReturn) {
      html = (
        <div className="search-display">
          <p className="searching">Searching ...</p>
        </div>
      )
    } else if (!funcFindButton && searchReturn) {
      let query = []
      if (search.exactMatch) query.push(' exactly')
      if (search.searchSource)
        query.push(
          <span key="source" className="query">
            <span className="param">{search.searchSource}</span>in source{' '}
          </span>,
        )
      if (search.searchTarget)
        query.push(
          <span key="target" className="query">
            <span className="param">{search.searchTarget}</span>in target{' '}
          </span>,
        )
      if (search.selectStatus !== 'all') {
        let statusLabel = (
          <span key="status">
            {' '}
            and status <span className="param">{search.selectStatus}</span>
          </span>
        )
        query.push(statusLabel)
      }
      let caseLabel =
        ' (' + (search.matchCase ? 'case sensitive' : 'case insensitive') + ')'
      query.push(caseLabel)
      let searchMode =
        search.searchSource !== '' && search.searchTarget !== ''
          ? 'source&target'
          : 'normal'
      let numbers = ''
      let totalResults = searchResults.length
      if (searchMode === 'source&target') {
        let total = searchResults.length ? searchResults.length : 0
        let label = total === 1 ? 'segment' : 'segments'
        numbers =
          total > 0 ? (
            <span key="numbers" className="numbers">
              Found <span className="segments">{searchResults.length}</span>{' '}
              {label}
            </span>
          ) : (
            <span key="numbers" className="numbers">
              No segments found
            </span>
          )
      } else {
        let total2 = total ? parseInt(total) : 0
        let label = total2 === 1 ? 'result' : 'results'
        let label2 = total2 === 1 ? 'segment' : 'segments'
        numbers =
          total2 > 0 ? (
            <span key="numbers" className="numbers">
              Found
              <span className="results">{' ' + total}</span>{' '}
              <span>{label}</span> in
              <span className="segments">
                {' ' + searchResults.length}
              </span>{' '}
              <span>{label2}</span>
            </span>
          ) : (
            <span key="numbers" className="numbers">
              No segments found
            </span>
          )
      }
      html = (
        <div className="search-display">
          <p className="found">
            {numbers} having
            {query}
          </p>
          {searchResults.length > 0 ? (
            <div className="search-result-buttons">
              <p>{segmentIndex + 1 + ' of ' + totalResults + ' segments'}</p>
              <Button
                size={BUTTON_SIZE.ICON_STANDARD}
                mode={BUTTON_MODE.OUTLINE}
                onClick={goToPrev}
                tooltip={'Find Previous (Shift + F3)'}
              >
                <ChevronLeft />
              </Button>
              <Button
                onClick={goToNext}
                mode={BUTTON_MODE.OUTLINE}
                size={BUTTON_SIZE.ICON_STANDARD}
                tooltip={'Find Next (F3)'}
              >
                <ChevronRight />
              </Button>
            </div>
          ) : null}
        </div>
      )
    }
    return html
  }

  latestRef.current = {
    active: props.active,
    search,
    handleSubmit,
    goToPrev,
    goToNext,
    handleCancelClick,
    handleClearClick,
    resetStatusFilter,
  }

  // mirrors componentDidUpdate: runs after every update (not on mount).
  // Deliberately no dependency array: this must run after every render,
  // exactly like componentDidUpdate does, not just when specific values
  // change (see jobIsSplittedRef, which is recomputed on every update).
  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => {
    if (isFirstRender.current) {
      isFirstRender.current = false
      prevActiveRef.current = props.active
      return
    }

    const prevActive = prevActiveRef.current

    if (props.active) {
      jobIsSplittedRef.current = CommonUtils.checkJobIsSplitted()
      if (!prevActive) {
        if (sourceElRef.current && focus) {
          sourceElRef.current.focus()
          setFocus(false)
        }
      }
    } else {
      if (!focus) {
        setFocus(true)
      }
    }

    // reset tag projection
    if (!props.active && prevActive) {
      setPreviousIsTagProjectionEnabled(false)
    }
    if (
      !props.active &&
      props.active !== prevActive &&
      previousIsTagProjectionEnabled
    ) {
      SegmentActions.changeTagProjectionStatus(true)
    }

    prevActiveRef.current = props.active
  })

  useEffect(() => {
    document.addEventListener('keydown', handelKeydownFunction, true)
    CatToolStore.addListener(CattolConstants.STORE_SEARCH_RESULT, setResults)
    CatToolStore.addListener(CattolConstants.CLOSE_SEARCH, handleCancelClick)
    SegmentStore.addListener(SegmentConstants.UPDATE_SEARCH, updateSearch)

    return () => {
      document.removeEventListener('keydown', handelKeydownFunction)
      CatToolStore.removeListener(
        CattolConstants.STORE_SEARCH_RESULT,
        setResults,
      )
      CatToolStore.removeListener(
        CattolConstants.CLOSE_SEARCH,
        handleCancelClick,
      )
      SegmentStore.removeListener(SegmentConstants.UPDATE_SEARCH, updateSearch)
    }
  }, [
    handelKeydownFunction,
    setResults,
    handleCancelClick,
    updateSearch,
  ])

  let statusOptions = config.searchable_statuses.map((item) => {
    return {
      name: (
        <>
          <div
            className={'status-dot ' + item.label.toLowerCase() + '-color'}
          />
          {item.label}
        </>
      ),
      id: item.value,
    }
  })
  if (config.secondRevisionsCount) {
    statusOptions.push({
      name: (
        <>
          <div className={'status-dot approved-2ndpass-color'} />
          APPROVED
        </>
      ),
      id: 'APPROVED-2',
    })
  }
  let findIsDisabled = true
  if (search.searchTarget !== '' || search.searchSource !== '') {
    findIsDisabled = false
  }
  let findButtonDisabled = !funcFindButton || findIsDisabled
  let statusDropdownClass =
    search.selectStatus !== '' && search.selectStatus !== 'all'
      ? 'filtered'
      : 'not-filtered'
  let statusDropdownDisabled =
    search.searchTarget !== '' || search.searchSource !== '' ? '' : 'disabled'
  let replaceCheckboxClass = search.searchTarget ? '' : 'disabled'
  // REPLACE needs what REPLACE ALL needs: a replacement to apply, and hits to
  // apply it to. It used to additionally require `funcFindButton` to be spent
  // and `isSelectedTag` to be false. The latter is a latch — the only thing that
  // ever sets it is a tag decorator deciding the current hit falls inside its
  // range, and nothing ever sets it back — so one false positive left REPLACE
  // dead until the next search or navigation, with REPLACE ALL still enabled.
  let replaceDisabled = !(
    search.enableReplace &&
    search.searchTarget &&
    searchReturn &&
    total > 0
  )
  let replaceAllDisabled = !(search.enableReplace && search.searchTarget)
  let clearVisible =
    search.searchTarget !== '' ||
    search.searchSource !== '' ||
    (search.selectStatus !== '' && search.selectStatus !== 'all')
  return props.active ? (
    <div className="search-form">
      <div className="find-wrapper">
        <div className="find-container">
          <div className="find-container-inside">
            <div className="find-list">
              <div className="find-element">
                <div className="find-in-source">
                  <input
                    type="text"
                    tabIndex={1}
                    value={search.searchSource}
                    placeholder="Find in source"
                    onKeyDown={(e) => handleKeyDown(e, 'searchSource')}
                    onChange={(e) => handleInputChange('searchSource', e)}
                    ref={sourceElRef}
                  />
                </div>
                <div className="find-exact-match">
                  <div className="exact-match">
                    <input
                      type="checkbox"
                      tabIndex={3}
                      checked={search.matchCase}
                      onChange={(e) => handleInputChange('matchCase', e)}
                      ref={matchCaseCheckRef}
                    />
                    <label> Match Case</label>
                  </div>
                  <div className="exact-match">
                    <input
                      ref={sourceInputRef}
                      type="checkbox"
                      tabIndex={4}
                      checked={search.exactMatch}
                      onChange={(e) => handleInputChange('exactMatch', e)}
                    />
                    <label> Whole word</label>
                  </div>
                </div>
              </div>
              <div className="find-element-container">
                <div className="find-element">
                  <div className="find-in-target">
                    <input
                      ref={targetInputRef}
                      type="text"
                      tabIndex={2}
                      placeholder="Find in target"
                      value={search.searchTarget}
                      onChange={(e) => handleInputChange('searchTarget', e)}
                      onKeyDown={(e) => handleKeyDown(e, 'searchTarget')}
                      className={
                        !search.searchTarget && search.enableReplace
                          ? 'warn'
                          : null
                      }
                    />
                  </div>
                  {showReplaceOptionsInSearch ? (
                    <div
                      className={'enable-replace-check ' + replaceCheckboxClass}
                    >
                      <input
                        type="checkbox"
                        tabIndex={5}
                        checked={search.enableReplace}
                        onChange={(e) => handleInputChange('enableReplace', e)}
                      />
                      <label> Replace with</label>
                    </div>
                  ) : null}
                </div>
                {showReplaceOptionsInSearch && search.enableReplace ? (
                  <div className="find-element">
                    <div className="find-in-replace">
                      <input
                        type="text"
                        placeholder="Replace in target"
                        value={search.replaceTarget}
                        onChange={(e) => handleInputChange('replaceTarget', e)}
                      />
                    </div>
                  </div>
                ) : null}
              </div>
              <div className="find-element find-dropdown-status">
                <Select
                  options={statusOptions}
                  className={
                    'find-dropdown ' +
                    statusDropdownClass +
                    ' ' +
                    statusDropdownDisabled
                  }
                  isDisabled={
                    search.searchTarget === '' && search.searchSource === ''
                  }
                  onSelect={(value) => {
                    handleStatusChange(value.id)
                  }}
                  activeOption={
                    statusOptions.find(
                      (item) => item.id === search.selectStatus,
                    ) || undefined
                  }
                  placeholder={'Status segment'}
                  checkSpaceToReverse={false}
                  showResetButton={true}
                  resetFunction={() => handleStatusChange('all')}
                />
              </div>
              <div className="find-element find-clear-all">
                {clearVisible ? (
                  <div className="find-clear">
                    <button
                      type="button"
                      className=""
                      onClick={handleClearClick}
                    >
                      Clear
                    </button>
                  </div>
                ) : null}
              </div>
            </div>
            {showReplaceOptionsInSearch ? (
              <div>
                <div className="find-actions">
                  <Button
                    mode={BUTTON_MODE.OUTLINE}
                    onClick={handleSubmit}
                    disabled={findButtonDisabled}
                  >
                    FIND
                  </Button>
                  <Button
                    mode={BUTTON_MODE.OUTLINE}
                    onClick={handleReplaceClick}
                    disabled={replaceDisabled}
                  >
                    REPLACE
                  </Button>
                  <Button
                    mode={BUTTON_MODE.OUTLINE}
                    onClick={handleReplaceAllClick}
                    disabled={replaceAllDisabled}
                  >
                    REPLACE ALL
                  </Button>
                </div>
                {jobIsSplittedRef.current && (
                  <div className="find-option">
                    <input
                      type="checkbox"
                      tabIndex={5}
                      checked={search.entireJob}
                      onChange={(e) => handleInputChange('entireJob', e)}
                    />
                    <label> Search all chunks</label>
                  </div>
                )}
              </div>
            ) : (
              <div className="find-actions">
                <Button
                  mode={BUTTON_MODE.OUTLINE}
                  onClick={handleSubmit}
                  disabled={findButtonDisabled}
                >
                  FIND
                </Button>
              </div>
            )}
          </div>
          {getResultsHtml()}
        </div>
      </div>
    </div>
  ) : null
}

export default Search
