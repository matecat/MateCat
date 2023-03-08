import React from 'react'
import _ from 'lodash'

import CatToolConstants from '../../../../constants/CatToolConstants'
import CatToolStore from '../../../../stores/CatToolStore'
import SegmentFilterUtils from './segment_filter'
import SegmentActions from '../../../../actions/SegmentActions'

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
    this.dropdownInitialized = false

    SegmentFilterUtils.initEvents()
  }

  defaultState() {
    let storedState = {}

    if (storedState.reactState) {
      storedState.reactState.moreFilters = this.moreFilters
      return storedState.reactState
    } else {
      return {
        selectedStatus: '',
        samplingType: '',
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
    $(this.statusDropdown).dropdown('restore defaults')
  }

  resetMoreFilter() {
    $(this.filtersDropdown).dropdown('restore defaults')
  }

  resetDataSampleFilter() {
    $(this.dataSampleDropDown).dropdown('restore defaults')
  }

  clearClick(e) {
    e.preventDefault()
    SegmentFilterUtils.clearFilter()
    this.resetState()
    $(this.filtersDropdown).dropdown('restore defaults')
    $(this.dataSampleDropDown).dropdown('restore defaults')
    $(this.statusDropdown).dropdown('restore defaults')
    $(this.toggleFilters).checkbox('set unchecked')
  }

  closeClick(e) {
    e.preventDefault()
    this.dropdownInitialized = false
    SegmentFilterUtils.closeFilter()
  }

  doSubmitFilter() {
    let sample
    if (this.applyFilters) return //updating the dropdown
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
    if (sample || this.state.selectedStatus !== '') {
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
      value = 'APPROVED'
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
        samplingType: '',
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

  // humanSampleType() {
  //     let map = {
  //         'segment_length_high_to_low': 'Segment length (high to low)',
  //         'segment_length_low_to_high': 'Segment length (low to high)',
  //         'regular_intervals': 'Regular intervals',
  //         'edit_distance_high_to_low': 'Edit distance (high to low)',
  //         'edit_distance_low_to_high': 'Edit distance (low to high)'
  //     };
  //
  //     return map[this.state.samplingType];
  // }

  samplingSizeChanged() {
    let value = parseInt(this.sampleSizeInput.value)
    if (value > 100 || value < 1) return false

    this.setState({
      samplingSize: value,
    })
  }

  moveUp() {
    if (this.state.filtering && this.state.filteredCount > 1) {
      UI.gotoPreviousSegment()
    }
  }

  moveDown() {
    if (this.state.filtering && this.state.filteredCount > 1) {
      UI.gotoNextSegment()
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
    if (_.isUndefined(state)) {
      this.setState({
        filteredCount: data.count,
        filtering: true,
        segmentsArray: data.segment_ids,
        filterSubmitted: false,
      })
    } else {
      this.applyFilters = true
      state.filteredCount = data.count
      state.filtering = true
      state.segmentsArray = data.segment_ids
      state.filterSubmitted = false
      this.setState(state)
      setTimeout(this.updateObjects.bind(this))
    }
  }

  initDropDown() {
    let self = this
    if (this.props.active && !this.dropdownInitialized) {
      this.dropdownInitialized = true
      $(this.statusDropdown).dropdown({
        onChange: function (value) {
          self.filterSelectChanged(value)
        },
      })
      $(this.filtersDropdown).dropdown({
        onChange: function (value) {
          self.moreFilterSelectChanged(value)
        },
      })
      $(this.dataSampleDropDown).dropdown({
        onChange: function (value) {
          self.dataSampleChange(value)
        },
      })
      $(this.toggleFilters).checkbox({
        onChecked: function () {
          $(self.filtersDropdown).dropdown('restore defaults')
          self.setState({
            filtersEnabled: false,
            dataSampleEnabled: true,
            samplingType: '',
          })
        },
        onUnchecked: function () {
          $(self.dataSampleDropDown).dropdown('restore defaults')
          self.setState({
            filtersEnabled: true,
            dataSampleEnabled: false,
            samplingType: '',
          })
        },
      })
    }

    if (!this.props.active) {
      this.dropdownInitialized = false
    }
  }

  updateObjects() {
    if (this.applyFilters) {
      $(this.statusDropdown).dropdown('set selected', this.state.selectedStatus)
      if (!this.state.dataSampleEnabled) {
        $(this.filtersDropdown).dropdown(
          'set selected',
          this.state.samplingType,
        )
        $(this.toggleFilters).checkbox('set unchecked')
      } else {
        $(this.dataSampleDropDown).dropdown(
          'set selected',
          this.state.samplingType,
        )
        $(this.toggleFilters).checkbox('set checked')
      }
      this.applyFilters = false
    }
  }

  componentDidMount() {
    CatToolStore.addListener(
      CatToolConstants.SET_SEGMENT_FILTER,
      this.setFilter,
    )
    CatToolStore.addListener(
      CatToolConstants.RELOAD_SEGMENT_FILTER,
      this.doSubmitFilter,
    )
    this.initDropDown()
  }

  componentDidUpdate() {
    this.initDropDown()
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
      CatToolConstants.RELOAD_SEGMENT_FILTER,
      this.doSubmitFilter,
    )
  }

  render() {
    let buttonArrowsClass = 'qa-arrows-disbled'
    let options = config.searchable_statuses.map((item, index) => {
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
    let moreOptions = this.state.moreFilters.map(function (item, index) {
      return (
        <div key={index} data-value={item.value} className="item">
          {item.label}
        </div>
      )
    })

    if (this.state.filtering && this.state.filteredCount > 1) {
      buttonArrowsClass = 'qa-arrows-enabled'
    }

    let filterClassEnabled = !this.state.dataSampleEnabled ? '' : 'disabled'
    let dataSampleClassEnabled = this.state.dataSampleEnabled ? '' : 'disabled'
    let statusFilterClass =
      this.state.selectedStatus !== '' ? 'filtered' : 'not-filtered'
    filterClassEnabled =
      !this.state.dataSampleEnabled && this.state.samplingType !== ''
        ? filterClassEnabled + ' filtered'
        : filterClassEnabled + ' not-filtered'
    dataSampleClassEnabled =
      this.state.dataSampleEnabled && this.state.samplingType !== ''
        ? dataSampleClassEnabled + ' filtered'
        : dataSampleClassEnabled + ' not-filtered'

    return this.props.active ? (
      <div className="filter-wrapper">
        <div className="filter-container">
          <div className="filter-container-inside">
            <div className="filter-list">
              <div className="filter-dropdown">
                <div className={'filter-status ' + statusFilterClass}>
                  <div
                    className="ui top left pointing dropdown basic tiny button"
                    ref={(dropdown) => (this.statusDropdown = dropdown)}
                  >
                    <div className="text">
                      <div>Segment Status</div>
                    </div>
                    <div className="ui cancel label">
                      <i
                        className="icon-cancel3"
                        onClick={this.resetStatusFilter.bind(this)}
                      />
                    </div>
                    <div className="menu">{options}</div>
                  </div>
                </div>
              </div>
              <div className="filter-dropdown">
                <div className={'filter-activities ' + filterClassEnabled}>
                  <div
                    className="ui top left pointing dropdown basic tiny button"
                    ref={(dropdown) => (this.filtersDropdown = dropdown)}
                  >
                    <div className="text">Others</div>
                    <div className="ui cancel label">
                      <i
                        className="icon-cancel3"
                        onClick={this.resetMoreFilter.bind(this)}
                      />
                    </div>
                    <div className="menu">{moreOptions}</div>
                  </div>
                </div>
              </div>

              {config.isReview ? (
                <div className="filter-dropdown">
                  <div className="filter-toggle">
                    <div
                      className="ui toggle checkbox"
                      ref={(checkbox) => (this.toggleFilters = checkbox)}
                    >
                      <input type="checkbox" name="public" />
                    </div>
                  </div>
                  <div
                    className={'filter-data-sample ' + dataSampleClassEnabled}
                  >
                    <div
                      className="ui top left pointing dropdown basic tiny button"
                      ref={(checkbox) => (this.dataSampleDropDown = checkbox)}
                    >
                      <div className="text">Data Sample</div>
                      <div className="ui cancel label">
                        <i
                          className="icon-cancel3"
                          onClick={this.resetDataSampleFilter.bind(this)}
                        />
                      </div>
                      <div className="menu">
                        <div className="head-dropdown">
                          <div className="ui mini input">
                            <label>
                              Sample size <b>(%)</b>
                            </label>
                            <input
                              type="number"
                              placeholder="n°"
                              value={this.state.samplingSize}
                              onChange={this.samplingSizeChanged.bind(this)}
                              ref={(input) => (this.sampleSizeInput = input)}
                            />
                          </div>
                        </div>
                        <div className="divider" />
                        <div
                          className="item"
                          data-value="edit_distance_high_to_low"
                        >
                          <div className="type-item">Edit distance </div>
                          <div className="order-item"> (A - Z)</div>
                        </div>
                        <div
                          className="item"
                          data-value="edit_distance_low_to_high"
                        >
                          <div className="type-item">Edit distance</div>
                          <div className="order-item"> (Z - A)</div>
                        </div>
                        <div
                          className="item"
                          data-value="segment_length_high_to_low"
                        >
                          <div className="type-item">Segment length</div>
                          <div className="order-item"> (A - Z)</div>
                        </div>
                        <div
                          className="item"
                          data-value="segment_length_low_to_high"
                        >
                          <div className="type-item">Segment length</div>
                          <div className="order-item"> (Z - A)</div>
                        </div>
                        <div className="item" data-value="regular_intervals">
                          Regular interval
                        </div>
                      </div>
                    </div>
                    {this.state.dataSampleEnabled &&
                    this.state.samplingType !== '' ? (
                      <div className="percent-item">
                        {this.state.samplingSize}%
                      </div>
                    ) : null}
                  </div>
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
