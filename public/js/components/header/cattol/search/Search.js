import React from 'react'
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
import ConfirmMessageModal from '../../../modals/ConfirmMessageModal'
import AlertModal from '../../../modals/AlertModal'
import ModalsActions from '../../../../actions/ModalsActions'
import {tagSignatures} from '../../../segments/utils/DraftMatecatUtils/tagModel'
import CommonUtils from '../../../../utils/commonUtils'
import {
  REVISE_STEP_NUMBER,
  SEGMENTS_STATUS,
} from '../../../../constants/Constants'
import {Select} from '../../../common/Select'
import {segmentTranslation} from '../../../../setTranslationUtil'
import {Button, BUTTON_MODE} from '../../../common/Button/Button'

class Search extends React.Component {
  constructor(props) {
    super(props)
    this.defaultState = {
      isReview: props.isReview,
      searchable_statuses: props.searchable_statuses,
      showReplaceOptionsInSearch: true,
      search: {
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
      },
      focus: true,
      funcFindButton: true, // true=find / false=next
      total: null,
      searchReturn: false,
      searchResults: [],
      occurrencesList: [],
      searchResultsDictionary: {},
      featuredSearchResult: null,
    }
    this.state = cloneDeep(this.defaultState)

    this.handleSubmit = this.handleSubmit.bind(this)
    this.handleCancelClick = this.handleCancelClick.bind(this)
    this.handleInputChange = this.handleInputChange.bind(this)
    this.handleReplaceAllClick = this.handleReplaceAllClick.bind(this)
    this.handleReplaceClick = this.handleReplaceClick.bind(this)
    this.replaceTargetOnFocus = this.replaceTargetOnFocus.bind(this)
    this.handelKeydownFunction = this.handelKeydownFunction.bind(this)
    this.updateSearch = this.updateSearch.bind(this)
    this.jobIsSplitted = false
  }

  handleSubmit() {
    if (this.state.funcFindButton) {
      SearchUtils.execFind(this.state.search)
    }

    const {guess_tag: guessTag} = this.props.userInfo.metadata

    this.setState({
      funcFindButton: false,
      ...(guessTag === 1 && {
        previousIsTagProjectionEnabled: true,
      }),
    })
    // disable tag projection
    if (guessTag === 1) {
      SegmentActions.changeTagProjectionStatus(false)
    }
  }

  setResults(data) {
    this.setState({
      total: data.total,
      searchResults: data.searchResults,
      occurrencesList: data.occurrencesList,
      searchResultsDictionary: data.searchResultsDictionary,
      featuredSearchResult: data.featuredSearchResult,
      searchReturn: true,
      isSelectedTag: false,
    })
    setTimeout(() => {
      !isUndefined(this.state.occurrencesList[data.featuredSearchResult]) &&
        SegmentActions.openSegment(
          this.state.occurrencesList[data.featuredSearchResult],
        )
    })
  }

  updateSearch() {
    if (this.props.active) {
      setTimeout(() => {
        const searchObject = SearchUtils.updateSearchObject()
        this.setState({
          searchResults: searchObject.searchResults,
          occurrencesList: searchObject.occurrencesList,
          searchResultsDictionary: searchObject.searchResultsDictionary,
          featuredSearchResult: searchObject.featuredSearchResult,
          isSelectedTag: false,
        })
        setTimeout(() =>
          SegmentActions.addSearchResultToSegments(
            searchObject.occurrencesList,
            searchObject.searchResultsDictionary,
            this.state.featuredSearchResult,
            searchObject.searchParams,
          ),
        )
      })
    }
  }

  updateAfterReplace(sid) {
    let {searchResults} = this.state
    let itemReplaced = find(searchResults, (item) => item.id === sid)
    let total = this.state.total
    total--
    if (itemReplaced.occurrences.length === 1) {
      remove(searchResults, (item) => item.id === sid)
    }
    let newResultArray = map(searchResults, (item) => item.id)
    const searchObject =
      SearchUtils.updateSearchObjectAfterReplace(newResultArray)
    this.setState({
      total: total,
      searchResults: searchObject.searchResults,
      occurrencesList: searchObject.occurrencesList,
      searchResultsDictionary: searchObject.searchResultsDictionary,
    })
    CatToolActions.storeSearchResults({
      total: total,
      searchResults: searchObject.searchResults,
      occurrencesList: searchObject.occurrencesList,
      searchResultsDictionary: searchObject.searchResultsDictionary,
      featuredSearchResult: this.state.featuredSearchResult,
    })
    SegmentActions.addSearchResultToSegments(
      searchObject.occurrencesList,
      searchObject.searchResultsDictionary,
      this.state.featuredSearchResult,
      searchObject.searchParams,
    )
  }

  goToNext() {
    this.setFeatured(this.state.featuredSearchResult + 1)
  }

  goToPrev() {
    this.setFeatured(this.state.featuredSearchResult - 1)
  }

  setFeatured(value) {
    if (this.state.occurrencesList.length > 1) {
      let module = this.state.occurrencesList.length
      value = this.mod(value, module)
    } else {
      value = 0
    }
    SearchUtils.updateFeaturedResult(value)
    CatToolActions.storeSearchResults({
      total: this.state.total,
      searchResults: this.state.searchResults,
      occurrencesList: this.state.occurrencesList,
      searchResultsDictionary: this.state.searchResultsDictionary,
      featuredSearchResult: value,
    })
    SegmentActions.changeCurrentSearchSegment(value)
  }

  // handling module
  mod(n, m) {
    return ((n % m) + m) % m
  }

  handleCancelClick() {
    SearchUtils.searchOpen = false
    this.handleClearClick()
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

    this.resetStatusFilter()
    setTimeout(() => {
      CatToolActions.closeSubHeader()
      SegmentActions.removeSearchResultToSegments()
      this.setState(cloneDeep(this.defaultState))
    })
  }

  handleClearClick() {
    // SearchUtils.clearSearchMarkers();
    this.resetStatusFilter()
    setTimeout(() => {
      this.setState(cloneDeep(this.defaultState))
      SegmentActions.removeSearchResultToSegments()
    })
  }

  handleKeyDown(e, name) {
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
      this.handleInputChange(name, e)
    }
  }

  resetStatusFilter() {
    this.handleStatusChange('all')
  }

  handleReplaceAllClick(event) {
    event.preventDefault()
    let props = {
      modalName: 'confirmReplace',
      text: 'Do you really want to replace this text in all search results?',
      successText: 'Continue',
      successCallback: () => {
        SearchUtils.execReplaceAll(this.state.search)
          .then(() => {
            const currentId = SegmentStore.getCurrentSegmentId()
            SegmentActions.removeAllSegments()
            CatToolActions.onRender({
              firstLoad: false,
              segmentToOpen: currentId,
            })
          })
          .catch((errors) => {
            ModalsActions.showModalComponent(
              AlertModal,
              {
                text: errors?.length
                  ? errors[0].message
                  : 'We got an error, please contact support',
              },
              'Replace All Alert',
            )
          })
        ModalsActions.onCloseModal()
        CatToolActions.storeSearchResults({
          total: 0,
          searchResults: [],
          occurrencesList: [],
          searchResultsDictionary: {},
          featuredSearchResult: null,
        })
      },
      cancelText: 'Cancel',
      cancelCallback: function () {
        ModalsActions.onCloseModal()
      },
    }
    ModalsActions.showModalComponent(
      ConfirmMessageModal,
      props,
      'Confirmation required',
    )
  }

  handleReplaceClick() {
    if (this.state.search.searchTarget === this.state.search.replaceTarget) {
      ModalsActions.showModalComponent(
        AlertModal,
        {
          text: 'Attention: you are replacing the same text!',
        },
        'Replace Alert',
      )
      return false
    }

    SegmentActions.replaceCurrentSearch(this.state.search.replaceTarget)

    setTimeout(() => {
      const segment = SegmentStore.getSegmentByIdToJS(
        this.state.occurrencesList[this.state.featuredSearchResult],
      )
      if (segment) {
        this.updateAfterReplace(segment.original_sid)
        segmentTranslation(segment, segment.status, () => {}, false)
      }
    })
  }

  handleStatusChange(value) {
    let search = cloneDeep(this.state.search)
    search['selectStatus'] = value
    if (value === 'APPROVED-2') {
      search.revisionNumber = REVISE_STEP_NUMBER.REVISE2
      search['selectStatus'] = 'APPROVED'
    } else if (value === 'APPROVED') {
      search.revisionNumber = REVISE_STEP_NUMBER.REVISE1
    }
    this.setState({
      search: search,
      funcFindButton: true,
    })
  }

  handleInputChange(name, event) {
    //serch model
    const target = event.target
    const value = target.type === 'checkbox' ? target.checked : target.value
    let search = this.state.search
    search[name] = value

    if (name !== 'enableReplace') {
      this.setState({
        search: search,
        funcFindButton: true,
        total: null,
        searchReturn: false,
        searchResults: [],
        occurrencesList: [],
        searchResultsDictionary: {},
        featuredSearchResult: null,
      })
    } else {
      this.setState({
        search: search,
      })
    }
  }

  replaceTargetOnFocus() {
    let search = this.state.search
    search.enableReplace = true
    this.setState({
      search: search,
    })
  }

  componentDidUpdate(prevProps) {
    if (this.props.active) {
      this.jobIsSplitted = CommonUtils.checkJobIsSplitted()
      if (!prevProps.active) {
        if (this.sourceEl && this.state.focus) {
          this.sourceEl.focus()
          this.setState({
            focus: false,
          })
        }
      }
    } else {
      if (!this.state.focus) {
        this.setState({
          focus: true,
        })
      }
    }

    // reset tag projection
    if (!this.props.active && prevProps.active) {
      this.setState({
        previousIsTagProjectionEnabled: false,
      })
    }
    if (
      !this.props.active &&
      this.props.active !== prevProps.active &&
      this.state.previousIsTagProjectionEnabled
    ) {
      SegmentActions.changeTagProjectionStatus(true)
    }
  }
  getResultsHtml() {
    var html = ''
    const {featuredSearchResult, searchReturn, occurrencesList, searchResults} =
      this.state
    const segmentIndex = findIndex(
      searchResults,
      (item) => item.id === occurrencesList[featuredSearchResult],
    )
    //Waiting for results
    if (!this.state.funcFindButton && !searchReturn) {
      html = (
        <div className="search-display">
          <p className="searching">Searching ...</p>
        </div>
      )
    } else if (!this.state.funcFindButton && searchReturn) {
      let query = []
      if (this.state.search.exactMatch) query.push(' exactly')
      if (this.state.search.searchSource)
        query.push(
          <span key="source" className="query">
            <span className="param">{this.state.search.searchSource}</span>in
            source{' '}
          </span>,
        )
      if (this.state.search.searchTarget)
        query.push(
          <span key="target" className="query">
            <span className="param">{this.state.search.searchTarget}</span>in
            target{' '}
          </span>,
        )
      if (this.state.search.selectStatus !== 'all') {
        let statusLabel = (
          <span key="status">
            {' '}
            and status{' '}
            <span className="param">{this.state.search.selectStatus}</span>
          </span>
        )
        query.push(statusLabel)
      }
      let caseLabel =
        ' (' +
        (this.state.search.matchCase ? 'case sensitive' : 'case insensitive') +
        ')'
      query.push(caseLabel)
      let searchMode =
        this.state.search.searchSource !== '' &&
        this.state.search.searchTarget !== ''
          ? 'source&target'
          : 'normal'
      let numbers = ''
      let totalResults = this.state.searchResults.length
      if (searchMode === 'source&target') {
        let total = this.state.searchResults.length
          ? this.state.searchResults.length
          : 0
        let label = total === 1 ? 'segment' : 'segments'
        numbers =
          total > 0 ? (
            <span key="numbers" className="numbers">
              Found{' '}
              <span className="segments">
                {this.state.searchResults.length}
              </span>{' '}
              {label}
            </span>
          ) : (
            <span key="numbers" className="numbers">
              No segments found
            </span>
          )
      } else {
        let total = this.state.total ? parseInt(this.state.total) : 0
        let label = total === 1 ? 'result' : 'results'
        let label2 = total === 1 ? 'segment' : 'segments'
        numbers =
          total > 0 ? (
            <span key="numbers" className="numbers">
              Found
              <span className="results">{' ' + this.state.total}</span>{' '}
              <span>{label}</span> in
              <span className="segments">
                {' ' + this.state.searchResults.length}
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
          {this.state.searchResults.length > 0 ? (
            <div className="search-result-buttons">
              <p>{segmentIndex + 1 + ' of ' + totalResults + ' segments'}</p>

              <button
                className="ui basic tiny button"
                onClick={this.goToPrev.bind(this)}
              >
                <i className="icon-chevron-left" />
                <span> Find Previous (Shift + F3)</span>
              </button>
              <button
                className="ui basic tiny button"
                onClick={this.goToNext.bind(this)}
              >
                <i className="icon-chevron-right" />
                <span> Find Next (F3)</span>
              </button>
            </div>
          ) : null}
        </div>
      )
    }
    return html
  }
  handelKeydownFunction(event) {
    if (this.props.active) {
      if (event.keyCode === 27) {
        this.handleCancelClick()
      } else if (
        event.keyCode === 13 &&
        $(event.target).closest('.find-container').length > 0
      ) {
        if (
          this.state.search.searchTarget !== '' ||
          this.state.search.searchSource !== ''
        ) {
          event.preventDefault()
          this.handleSubmit()
        }
      } else if (event.key === 'F3' && event.shiftKey) {
        event.preventDefault()
        this.goToPrev()
      } else if (event.key === 'F3') {
        event.preventDefault()
        this.goToNext()
      }
    }
  }
  setStateReplaceButton = ({value}) => {
    setTimeout(() => {
      this.setState({
        isSelectedTag: value,
      })
    })
  }

  componentDidMount() {
    document.addEventListener('keydown', this.handelKeydownFunction, true)
    CatToolStore.addListener(
      CattolConstants.STORE_SEARCH_RESULT,
      this.setResults.bind(this),
    )
    CatToolStore.addListener(
      CattolConstants.CLOSE_SEARCH,
      this.handleCancelClick,
    )
    SegmentStore.addListener(SegmentConstants.UPDATE_SEARCH, this.updateSearch)
    SegmentStore.addListener(
      SegmentConstants.SET_IS_CURRENT_SEARCH_OCCURRENCE_TAG,
      this.setStateReplaceButton,
    )
  }
  componentWillUnmount() {
    document.removeEventListener('keydown', this.handelKeydownFunction)
    CatToolStore.removeListener(
      CattolConstants.STORE_SEARCH_RESULT,
      this.setResults,
    )
    CatToolStore.removeListener(
      CattolConstants.CLOSE_SEARCH,
      this.handleCancelClick,
    )
    SegmentStore.removeListener(
      SegmentConstants.UPDATE_SEARCH,
      this.updateSearch,
    )
    SegmentStore.removeListener(
      SegmentConstants.SET_IS_CURRENT_SEARCH_OCCURRENCE_TAG,
      this.setStateReplaceButton,
    )
  }

  render() {
    let statusOptions = config.searchable_statuses.map((item) => {
      return {
        name: (
          <>
            <div
              className={
                'ui ' + item.label.toLowerCase() + '-color empty circular label'
              }
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
            <div
              className={'ui ' + 'approved-2ndpass-color empty circular label'}
            />
            APPROVED
          </>
        ),
        id: 'APPROVED-2',
      })
    }
    let findIsDisabled = true
    if (
      this.state.search.searchTarget !== '' ||
      this.state.search.searchSource !== ''
    ) {
      findIsDisabled = false
    }
    let findButtonDisabled = !this.state.funcFindButton || findIsDisabled
    let statusDropdownClass =
      this.state.search.selectStatus !== '' &&
      this.state.search.selectStatus !== 'all'
        ? 'filtered'
        : 'not-filtered'
    let statusDropdownDisabled =
      this.state.search.searchTarget !== '' ||
      this.state.search.searchSource !== ''
        ? ''
        : 'disabled'
    let replaceCheckboxClass = this.state.search.searchTarget ? '' : 'disabled'
    let replaceDisabled = !(
      this.state.search.enableReplace &&
      this.state.search.searchTarget &&
      !this.state.funcFindButton &&
      !this.state.isSelectedTag
    )
    let replaceAllDisabled = !(
      this.state.search.enableReplace && this.state.search.searchTarget
    )
    let clearVisible =
      this.state.search.searchTarget !== '' ||
      this.state.search.searchSource !== '' ||
      (this.state.search.selectStatus !== '' &&
        this.state.search.selectStatus !== 'all')
    return this.props.active ? (
      <div className="ui form">
        <div className="find-wrapper">
          <div className="find-container">
            <div className="find-container-inside">
              <div className="find-list">
                <div className="find-element ui input">
                  <div className="find-in-source">
                    <input
                      type="text"
                      tabIndex={1}
                      value={this.state.search.searchSource}
                      placeholder="Find in source"
                      onKeyDown={(e) => this.handleKeyDown(e, 'searchSource')}
                      onChange={this.handleInputChange.bind(
                        this,
                        'searchSource',
                      )}
                      ref={(input) => (this.sourceEl = input)}
                    />
                  </div>
                  <div className="find-exact-match">
                    <div className="exact-match">
                      <input
                        type="checkbox"
                        tabIndex={3}
                        checked={this.state.search.matchCase}
                        onChange={this.handleInputChange.bind(
                          this,
                          'matchCase',
                        )}
                        ref={(checkbox) => (this.matchCaseCheck = checkbox)}
                      />
                      <label> Match Case</label>
                    </div>
                    <div className="exact-match">
                      <input
                        ref={(ref) => (this.sourceInput = ref)}
                        type="checkbox"
                        tabIndex={4}
                        checked={this.state.search.exactMatch}
                        onChange={this.handleInputChange.bind(
                          this,
                          'exactMatch',
                        )}
                      />
                      <label> Whole word</label>
                    </div>
                  </div>
                </div>
                <div className="find-element-container">
                  <div className="find-element ui input">
                    <div className="find-in-target">
                      <input
                        ref={(ref) => (this.targetInput = ref)}
                        type="text"
                        tabIndex={2}
                        placeholder="Find in target"
                        value={this.state.search.searchTarget}
                        onChange={this.handleInputChange.bind(
                          this,
                          'searchTarget',
                        )}
                        onKeyDown={(e) => this.handleKeyDown(e, 'searchTarget')}
                        className={
                          !this.state.search.searchTarget &&
                          this.state.search.enableReplace
                            ? 'warn'
                            : null
                        }
                      />
                    </div>
                    {this.state.showReplaceOptionsInSearch ? (
                      <div
                        className={
                          'enable-replace-check ' + replaceCheckboxClass
                        }
                      >
                        <input
                          type="checkbox"
                          tabIndex={5}
                          checked={this.state.search.enableReplace}
                          onChange={this.handleInputChange.bind(
                            this,
                            'enableReplace',
                          )}
                        />
                        <label> Replace with</label>
                      </div>
                    ) : null}
                  </div>
                  {this.state.showReplaceOptionsInSearch &&
                  this.state.search.enableReplace ? (
                    <div className="find-element ui input">
                      <div className="find-in-replace">
                        <input
                          type="text"
                          placeholder="Replace in target"
                          value={this.state.search.replaceTarget}
                          onChange={this.handleInputChange.bind(
                            this,
                            'replaceTarget',
                          )}
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
                      this.state.search.searchTarget === '' &&
                      this.state.search.searchSource === ''
                    }
                    onSelect={(value) => {
                      this.handleStatusChange(value.id)
                    }}
                    activeOption={
                      statusOptions.find(
                        (item) => item.id === this.state.search.selectStatus,
                      ) || undefined
                    }
                    placeholder={'Status Segment'}
                    checkSpaceToReverse={false}
                    showResetButton={true}
                    resetFunction={() => this.handleStatusChange('all')}
                  />
                </div>
                <div className="find-element find-clear-all">
                  {clearVisible ? (
                    <div className="find-clear">
                      <button
                        type="button"
                        className=""
                        onClick={this.handleClearClick.bind(this)}
                      >
                        Clear
                      </button>
                    </div>
                  ) : null}
                </div>
              </div>
              {this.state.showReplaceOptionsInSearch ? (
                <div>
                  <div className="find-actions">
                    <Button
                      mode={BUTTON_MODE.OUTLINE}
                      onClick={this.handleSubmit.bind(this)}
                      disabled={findButtonDisabled}
                    >
                      FIND
                    </Button>
                    <Button
                      mode={BUTTON_MODE.OUTLINE}
                      onClick={this.handleReplaceClick.bind(this)}
                      disabled={replaceDisabled}
                    >
                      REPLACE
                    </Button>
                    <Button
                      mode={BUTTON_MODE.OUTLINE}
                      onClick={this.handleReplaceAllClick.bind(this)}
                      disabled={replaceAllDisabled}
                    >
                      REPLACE ALL
                    </Button>
                  </div>
                  {this.jobIsSplitted && (
                    <div className="find-option">
                      <input
                        type="checkbox"
                        tabIndex={5}
                        checked={this.state.search.entireJob}
                        onChange={this.handleInputChange.bind(
                          this,
                          'entireJob',
                        )}
                      />
                      <label> Search all chunks</label>
                    </div>
                  )}
                </div>
              ) : (
                <div className="find-actions">
                  <Button
                    mode={BUTTON_MODE.OUTLINE}
                    onClick={this.handleSubmit.bind(this)}
                    disabled={findButtonDisabled}
                  >
                    FIND
                  </Button>
                </div>
              )}
            </div>
            {this.getResultsHtml()}
          </div>
        </div>
      </div>
    ) : null
  }
}

export default Search
