import React from 'react'
import _ from 'lodash'

import CattolConstants from '../../../../constants/CatToolConstants'
import SegmentStore from '../../../../stores/SegmentStore'
import CatToolStore from '../../../../stores/CatToolStore'
import SearchUtils from './searchUtils'
import SegmentConstants from '../../../../constants/SegmentConstants'
import SegmentActions from '../../../../actions/SegmentActions'
import CatToolActions from '../../../../actions/CatToolActions'
import ConfirmMessageModal from '../../../modals/ConfirmMessageModal'
import {ModalWindow} from '../../../modals/ModalWindow'
import AlertModal from '../../../modals/AlertModal'

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
        replaceTarget: '',
        selectStatus: 'all',
        searchTarget: '',
        searchSource: '',
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
    this.state = _.cloneDeep(this.defaultState)

    this.handleSubmit = this.handleSubmit.bind(this)
    this.handleCancelClick = this.handleCancelClick.bind(this)
    this.handleInputChange = this.handleInputChange.bind(this)
    this.handleReplaceAllClick = this.handleReplaceAllClick.bind(this)
    this.handleReplaceClick = this.handleReplaceClick.bind(this)
    this.replaceTargetOnFocus = this.replaceTargetOnFocus.bind(this)
    this.handelKeydownFunction = this.handelKeydownFunction.bind(this)
    this.updateSearch = this.updateSearch.bind(this)
    this.dropdownInit = false
  }

  resetSearch() {
    this.setState({
      total: null,
      searchReturn: false,
      searchResults: [],
      occurrencesList: [],
      searchResultsDictionary: {},
      featuredSearchResult: null,
    })
  }

  handleSubmit() {
    if (this.state.funcFindButton) {
      SearchUtils.execFind(this.state.search)
    }
    this.setState({
      funcFindButton: false,
    })
  }

  setResults(data) {
    this.setState({
      total: data.total,
      searchResults: data.searchResults,
      occurrencesList: data.occurrencesList,
      searchResultsDictionary: data.searchResultsDictionary,
      featuredSearchResult: data.featuredSearchResult,
      searchReturn: true,
    })
    setTimeout(() => {
      !_.isUndefined(this.state.occurrencesList[data.featuredSearchResult]) &&
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
    let itemReplaced = _.find(searchResults, (item) => item.id === sid)
    let total = this.state.total
    total--
    if (itemReplaced.occurrences.length === 1) {
      _.remove(searchResults, (item) => item.id === sid)
    }
    let newResultArray = _.map(searchResults, (item) => item.id)
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
    this.dropdownInit = false
    UI.body.removeClass('searchActive')
    this.handleClearClick()
    if (UI.segmentIsLoaded(UI.currentSegmentId)) {
      setTimeout(() => SegmentActions.scrollToSegment(UI.currentSegmentId))
    } else {
      UI.render({
        firstLoad: false,
        segmentToOpen: UI.currentSegmentId,
      })
    }

    this.resetStatusFilter()
    setTimeout(() => {
      CatToolActions.closeSubHeader()
      SegmentActions.removeSearchResultToSegments()
      this.setState(_.cloneDeep(this.defaultState))
    })
  }

  handleClearClick() {
    this.dropdownInit = false
    // SearchUtils.clearSearchMarkers();
    this.resetStatusFilter()
    setTimeout(() => {
      this.setState(_.cloneDeep(this.defaultState))
      SegmentActions.removeSearchResultToSegments()
    })
  }

  resetStatusFilter() {
    $(this.statusDropDown).dropdown('restore defaults')
  }

  handleReplaceAllClick(event) {
    event.preventDefault()
    let self = this
    let props = {
      modalName: 'confirmReplace',
      text: 'Do you really want to replace this text in all search results? <br>(The page will be refreshed after confirm)',
      successText: 'Continue',
      successCallback: function () {
        SearchUtils.execReplaceAll(self.state.search)
          .then(() => {
            const currentId = SegmentStore.getCurrentSegmentId()
            UI.unmountSegments()
            UI.render({
              firstLoad: false,
              segmentToOpen: currentId,
            })
          })
          .catch((errors) => {
            ModalWindow.showModalComponent(
              AlertModal,
              {
                text: errors[0].message,
              },
              'Replace All Alert',
            )
          })
        ModalWindow.onCloseModal()
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
        ModalWindow.onCloseModal()
      },
    }
    ModalWindow.showModalComponent(
      ConfirmMessageModal,
      props,
      'Confirmation required',
    )
  }

  handleReplaceClick() {
    if (this.state.search.searchTarget === this.state.search.replaceTarget) {
      ModalWindow.showModalComponent(
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
      this.updateAfterReplace(segment.original_sid)
      // let next = this.state.occurrencesList[this.state.featuredSearchResult];
      UI.setTranslation({
        id_segment: segment.original_sid,
        status: segment.status,
      })
    })
  }

  handleStatusChange(value) {
    let search = _.cloneDeep(this.state.search)
    search['selectStatus'] = value
    if (value === 'APPROVED-2') {
      search.revisionNumber = 2
      search['selectStatus'] = 'APPROVED'
    } else {
      search.revisionNumber = null
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
      if (!prevProps.active) {
        if (this.sourceEl && this.state.focus) {
          this.sourceEl.focus()
          this.setState({
            focus: false,
          })
        }
      }
      $('body').addClass('search-open')

      let self = this
      if (!this.dropdownInit) {
        this.dropdownInit = true
        $(this.statusDropDown).dropdown({
          onChange: function (value) {
            value = value === '' ? 'all' : value
            self.handleStatusChange(value)
          },
        })
      }
    } else {
      $('body').removeClass('search-open')
      if (!this.state.focus) {
        this.setState({
          focus: true,
        })
      }
      this.dropdownInit = false
    }
  }
  getResultsHtml() {
    var html = ''
    const {featuredSearchResult, searchReturn, occurrencesList, searchResults} =
      this.state
    const segmentIndex = _.findIndex(
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
  componentDidMount() {
    document.addEventListener('keydown', this.handelKeydownFunction, false)
    CatToolStore.addListener(
      CattolConstants.STORE_SEARCH_RESULT,
      this.setResults.bind(this),
    )
    CatToolStore.addListener(
      CattolConstants.CLOSE_SEARCH,
      this.handleCancelClick,
    )
    SegmentStore.addListener(SegmentConstants.UPDATE_SEARCH, this.updateSearch)
  }
  componentWillUnmount() {
    document.removeEventListener('keydown', this.handelKeydownFunction, false)
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
  }

  render() {
    let options = config.searchable_statuses.map(function (item, index) {
      return (
        <React.Fragment key={index}>
          <div className="item" key={index} data-value={item.value}>
            <div
              className={
                'ui ' + item.label.toLowerCase() + '-color empty circular label'
              }
            />
            {item.label}
          </div>
          {config.secondRevisionsCount && item.value === 'APPROVED' ? (
            <div className="item" key={index + '-2'} data-value={'APPROVED-2'}>
              <div
                className={
                  'ui ' +
                  item.label.toLowerCase() +
                  '-2ndpass-color empty circular label'
                }
              />
              {item.label}
            </div>
          ) : null}
        </React.Fragment>
      )
    })
    let findIsDisabled = true
    if (
      this.state.search.searchTarget !== '' ||
      this.state.search.searchSource !== ''
    ) {
      findIsDisabled = false
    }
    let findButtonClassDisabled =
      !this.state.funcFindButton || findIsDisabled ? 'disabled' : ''
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
    let replaceButtonsClass =
      this.state.search.enableReplace &&
      this.state.search.searchTarget &&
      !this.state.funcFindButton
        ? ''
        : 'disabled'
    let replaceAllButtonsClass =
      this.state.search.enableReplace && this.state.search.searchTarget
        ? ''
        : 'disabled'
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
                        type="text"
                        tabIndex={2}
                        placeholder="Find in target"
                        value={this.state.search.searchTarget}
                        onChange={this.handleInputChange.bind(
                          this,
                          'searchTarget',
                        )}
                        className={
                          !this.state.search.searchTarget &&
                          this.state.search.enableReplace
                            ? 'warn'
                            : null
                        }
                      />
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
                  <div
                    className={
                      'find-dropdown ' +
                      statusDropdownClass +
                      ' ' +
                      statusDropdownDisabled
                    }
                  >
                    <div
                      className="ui top left pointing dropdown basic tiny button"
                      ref={(dropdown) => (this.statusDropDown = dropdown)}
                    >
                      <div className="text">
                        <div>Status Segment</div>
                      </div>
                      <div
                        className="ui cancel label"
                        onClick={this.resetStatusFilter.bind(this)}
                      >
                        <i className="icon-cancel3" />
                      </div>
                      <div className="menu">{options}</div>
                    </div>
                  </div>
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
                <div className="find-actions">
                  <button
                    className={
                      'ui basic tiny button ' + findButtonClassDisabled
                    }
                    onClick={this.handleSubmit.bind(this)}
                  >
                    FIND
                  </button>
                  <button
                    className={'ui basic tiny button ' + replaceButtonsClass}
                    onClick={this.handleReplaceClick.bind(this)}
                  >
                    REPLACE
                  </button>
                  <button
                    className={'ui basic tiny button ' + replaceAllButtonsClass}
                    onClick={this.handleReplaceAllClick.bind(this)}
                  >
                    REPLACE ALL
                  </button>
                </div>
              ) : (
                <div className="find-actions">
                  <button
                    type="button"
                    className={
                      'ui basic tiny button ' + findButtonClassDisabled
                    }
                    onClick={this.handleSubmit.bind(this)}
                  >
                    FIND
                  </button>
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
