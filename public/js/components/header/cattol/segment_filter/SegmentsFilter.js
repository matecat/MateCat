import React from 'react'
import {isUndefined} from 'lodash'
import $ from 'jquery'

import CatToolConstants from '../../../../constants/CatToolConstants'
import CatToolStore from '../../../../stores/CatToolStore'
import SegmentFilterUtils from './segment_filter'
import SegmentActions from '../../../../actions/SegmentActions'
import {SEGMENTS_STATUS} from '../../../../constants/Constants'
import {Select} from '../../../common/Select'
import Switch from '../../../common/Switch'
import {DataSampleDropdown} from './DataSampleDropdown'

class SegmentsFilter extends React.Component {
  constructor(props) {
    super(props)
    this.moreFilters = [
      {value: 'ice', label: 'ICE'},
      {value: 'unlocked', label: 'Not ICE'},
      {value: 'modified_ice', label: 'Modified ICE'},
      {value: 'repetitions', label: 'Repetitions'},
      {value: 'mt', label: 'MT'},
      {value: 'matches', label: '100% Matches'},
      // {value: 'fuzzies_50_74', label: 'fuzzies_50_74'},
      {value: 'fuzzies_75_84', label: 'Fuzzies 75-84'},
      {value: 'fuzzies_85_94', label: 'Fuzzies 85-94'},
      {value: 'fuzzies_95_99', label: 'Fuzzies 95-99'},
      {value: 'todo', label: 'Todo'},
    ]
    this.state = this.defaultState()
    this.setFilter = this.setFilter.bind(this)
    this.moreFilterSelectChanged = this.moreFilterSelectChanged.bind(this)
    this.doSubmitFilter = this.doSubmitFilter.bind(this)

    SegmentFilterUtils.initEvents()
  }

  defaultState() {
    let storedState = {}

    if (storedState.reactState) {
      storedState.reactState.moreFilters = this.moreFilters
      return storedState.reactState
    } else {
      return {
        selectedStatus: undefined,
        samplingType: undefined,
        samplingSize: 5,
        filtering: false,
        filteredCount: 0,
        segmentsArray: [],
        moreFilters: this.moreFilters,
        filtersEnabled: true,
        dataSampleEnabled: false,
        filterSubmitted: false,
        revisionNumber: null,
      }
    }
  }

  resetState() {
    this.setState(this.defaultState())
  }

  resetStatusFilter() {
    this.setState({
      selectedStatus: undefined,
      revisionNumber: undefined,
    })
  }

  resetMoreFilter() {
    this.setState({
      samplingType: undefined,
    })
  }

  resetDataSampleFilter() {
    this.setState({
      samplingType: undefined,
      samplingSize: 5,
    })
  }

  clearClick(e) {
    e.preventDefault()
    SegmentFilterUtils.clearFilter()
    this.resetState()
    this.resetDataSampleFilter()
  }

  doSubmitFilter() {
    let sample
    if (this.state.samplingType) {
      if (this.state.dataSampleEnabled) {
        sample = {
          type: this.state.samplingType,
          size: this.state.samplingSize,
        }
      } else {
        sample = {
          type: this.state.samplingType,
        }
      }
    }
    if (sample || this.state.selectedStatus) {
      SegmentFilterUtils.filterSubmit(
        {
          status: this.state.selectedStatus,
          sample: sample,
          revision_number: this.state.revisionNumber,
        },
        {
          samplingType: this.state.samplingType,
          samplingSize: this.state.samplingSize,
          selectedStatus: this.state.selectedStatus,
          dataSampleEnabled: this.state.dataSampleEnabled,
        },
      )
      this.setState({
        filterSubmitted: true,
      })
    } else {
      this.setState({
        filtering: false,
      })
      setTimeout(() => SegmentFilterUtils.clearFilter())
    }
  }

  filterSelectChanged(value) {
    let revisionNumber
    if (value === 'APPROVED-2') {
      revisionNumber = 2
      value = SEGMENTS_STATUS.APPROVED2
    } else {
      revisionNumber = null
    }

    if (
      (!config.isReview &&
        value === 'TRANSLATED' &&
        this.state.samplingType === 'todo') ||
      (config.isReview &&
        value === 'APPROVED' &&
        this.state.samplingType === 'todo')
    ) {
      setTimeout(() => {
        this.resetMoreFilter()
      })

      this.setState({
        selectedStatus: value,
        samplingType: undefined,
        revisionNumber: revisionNumber,
      })
    } else {
      this.setState({
        selectedStatus: value,
        revisionNumber: revisionNumber,
      })
    }
    setTimeout(this.doSubmitFilter, 100)
  }

  moreFilterSelectChanged(value) {
    if (
      (!config.isReview &&
        this.state.selectedStatus === 'TRANSLATED' &&
        value === 'todo') ||
      (config.isReview &&
        this.state.selectedStatus === 'APPROVED' &&
        value === 'todo')
    ) {
      setTimeout(() => {
        this.resetStatusFilter()
      })
      this.setState({
        samplingType: value,
        selectedStatus: '',
      })
    } else {
      this.setState({
        samplingType: value,
      })
    }
    setTimeout(this.doSubmitFilter, 100)
  }

  dataSampleChange(value) {
    this.setState({
      samplingType: value,
    })
    setTimeout(this.doSubmitFilter, 100)
  }

  samplingSizeChanged(value) {
    this.setState({
      samplingSize: value,
    })
  }

  moveUp() {
    if (this.state.filtering && this.state.filteredCount > 1) {
      SegmentFilterUtils.gotoPreviousSegment()
    }
  }

  moveDown() {
    if (this.state.filtering && this.state.filteredCount > 1) {
      SegmentActions.gotoNextSegment()
    }
  }

  selectAllSegments(event) {
    event.stopPropagation()
    SegmentActions.setBulkSelectionSegments(this.state.segmentsArray.slice(0))
  }

  unlockAllSegments(event) {
    event.stopPropagation()
    SegmentActions.unlockSegments(this.state.segmentsArray.slice(0))
  }

  setFilter(data, state) {
    if (isUndefined(state)) {
      this.setState({
        filteredCount: data.count,
        filtering: true,
        segmentsArray: data.segment_ids,
        filterSubmitted: false,
      })
    } else {
      state.filteredCount = data.count
      state.filtering = true
      state.segmentsArray = data.segment_ids
      state.filterSubmitted = false
      this.setState(state)
      setTimeout(this.doSubmitFilter, 100)
    }
  }

  filterSegmentsError = () => {
    this.setState({
      filterSubmitted: false,
    })
  }

  onChangeToggle = (checked) => {
    if (checked) {
      this.setState({
        filtersEnabled: false,
        dataSampleEnabled: true,
        samplingType: undefined,
      })
    } else {
      this.setState({
        filtersEnabled: true,
        dataSampleEnabled: false,
        samplingType: undefined,
      })
    }
  }

  componentDidMount() {
    const segmentFilterData = SegmentFilterUtils.getStoredState()
    if (
      SegmentFilterUtils.enabled() &&
      segmentFilterData.reactState &&
      segmentFilterData.open
    )
      SegmentFilterUtils.openFilter()
    CatToolStore.addListener(
      CatToolConstants.SET_SEGMENT_FILTER,
      this.setFilter,
    )
    CatToolStore.addListener(
      CatToolConstants.SEGMENT_FILTER_ERROR,
      this.filterSegmentsError,
    )
    CatToolStore.addListener(
      CatToolConstants.RELOAD_SEGMENT_FILTER,
      this.doSubmitFilter,
    )
  }

  componentDidUpdate() {
    if (this.props.active) {
      $('#action-filter').addClass('open')
    } else {
      $('#action-filter').removeClass('open')
    }
  }

  componentWillUnmount() {
    CatToolStore.removeListener(
      CatToolConstants.SET_SEGMENT_FILTER,
      this.setFilter,
    )
    CatToolStore.removeListener(
      CatToolConstants.SEGMENT_FILTER_ERROR,
      this.filterSegmentsError,
    )
    CatToolStore.removeListener(
      CatToolConstants.RELOAD_SEGMENT_FILTER,
      this.doSubmitFilter,
    )
  }

  render() {
    let buttonArrowsClass = 'qa-arrows-disbled'
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
            APPROVED 2
          </>
        ),
        id: 'APPROVED-2',
      })
    }
    let moreOptions = this.state.moreFilters.map((item, index) => {
      return {
        name: item.label,
        id: item.value,
      }
    })

    if (this.state.filtering && this.state.filteredCount > 1) {
      buttonArrowsClass = 'qa-arrows-enabled'
    }

    let filterClassEnabled = !this.state.dataSampleEnabled ? '' : 'disabled'
    let statusFilterClass =
      this.state.selectedStatus !== '' ? 'filtered' : 'not-filtered'
    filterClassEnabled =
      !this.state.dataSampleEnabled && this.state.samplingType
        ? filterClassEnabled + ' filtered'
        : filterClassEnabled + ' not-filtered'

    return this.props.active ? (
      <div className="filter-wrapper">
        <div className="filter-container">
          <div className="filter-container-inside">
            <div className="filter-list">
              <div className="filter-dropdown">
                <Select
                  className={'filter-status ' + statusFilterClass}
                  options={statusOptions}
                  onSelect={(value) => {
                    this.filterSelectChanged(value.id)
                  }}
                  activeOption={
                    statusOptions.find(
                      (item) => item.id === this.state.selectedStatus,
                    ) || undefined
                  }
                  placeholder={'Segment status'}
                  checkSpaceToReverse={false}
                  showResetButton={true}
                  resetFunction={() => {
                    this.filterSelectChanged()
                  }}
                  maxHeightDroplist={200}
                />
              </div>
              <div className="filter-dropdown">
                <Select
                  className={'filter-activities ' + filterClassEnabled}
                  options={moreOptions}
                  onSelect={(value) => {
                    this.moreFilterSelectChanged(value.id)
                  }}
                  activeOption={
                    moreOptions.find(
                      (item) => item.id === this.state.samplingType,
                    ) || undefined
                  }
                  placeholder={'Others'}
                  checkSpaceToReverse={false}
                  showResetButton={true}
                  resetFunction={() => {
                    this.moreFilterSelectChanged()
                  }}
                  maxHeightDroplist={400}
                  enabled={!this.state.dataSampleEnabled}
                />
              </div>

              {config.isReview ? (
                <div className="filter-dropdown">
                  <Switch
                    onChange={(value) => {
                      this.onChangeToggle(value)
                    }}
                    active={this.state.dataSampleEnabled}
                    showText={false}
                  />
                  <DataSampleDropdown
                    onChange={(value) => this.dataSampleChange(value)}
                    isDisabled={!this.state.dataSampleEnabled}
                    onChangeSampleSize={(value) =>
                      this.samplingSizeChanged(value)
                    }
                    samplingSize={this.state.samplingSize}
                    samplingType={this.state.samplingType}
                    resetFunction={() => this.resetDataSampleFilter()}
                  />
                </div>
              ) : null}
              {this.state.filtering ? (
                <div className="clear-filter-element">
                  <div className="clear-filter">
                    <button href="#" onClick={this.clearClick.bind(this)}>
                      Clear all filters
                    </button>
                  </div>
                  {this.state.filteredCount > 0 ? (
                    <div className="select-all-filter">
                      <button
                        href="#"
                        ref={(button) => (this.selectAllButton = button)}
                        onClick={(event) => this.selectAllSegments(event)}
                      >
                        Select all filtered segments
                      </button>
                    </div>
                  ) : null}
                  {this.state.filteredCount > 0 &&
                  this.state.samplingType === 'ice' ? (
                    <div className="select-all-filter">
                      <button
                        href="#"
                        ref={(button) => (this.unlockIce = button)}
                        onClick={(event) => this.unlockAllSegments(event)}
                      >
                        Unlock all filtered segments
                      </button>
                    </div>
                  ) : null}
                </div>
              ) : null}
            </div>
            <div className="filter-navigator">
              <div className="filter-actions">
                {this.state.filtering &&
                this.state.filteredCount &&
                !this.state.filterSubmitted > 0 ? (
                  <div
                    className={
                      'filter-arrows filter-arrows-enabled ' + buttonArrowsClass
                    }
                  >
                    <div className="label-filters labl">
                      <b>{this.state.filteredCount}</b> Filtered segments
                    </div>
                    <button
                      className="filter-move-up ui basic button"
                      onClick={this.moveUp.bind(this)}
                    >
                      <i className="icon-chevron-left" />
                    </button>
                    <button
                      className="filter-move-up ui basic button"
                      onClick={this.moveDown.bind(this)}
                    >
                      <i className="icon-chevron-right" />
                    </button>
                  </div>
                ) : null}
                {this.state.filtering &&
                !this.state.filterSubmitted &&
                this.state.filteredCount === 0 ? (
                  <div
                    className={
                      'filter-arrows filter-arrows-enabled ' + buttonArrowsClass
                    }
                  >
                    <div className="label-filters labl">No segments found</div>
                  </div>
                ) : null}
                {this.state.filterSubmitted ? (
                  <div className="label-filters labl">
                    Applying filter
                    <div className="loader"></div>
                  </div>
                ) : null}
              </div>
            </div>
          </div>
        </div>
      </div>
    ) : null
  }
}

export default SegmentsFilter
