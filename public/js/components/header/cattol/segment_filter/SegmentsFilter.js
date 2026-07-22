import React, {useState, useEffect, useCallback, useRef} from 'react'
import {isUndefined} from 'lodash'

import CatToolConstants from '../../../../constants/CatToolConstants'
import CatToolStore from '../../../../stores/CatToolStore'
import SegmentFilterUtils from './segment_filter'
import SegmentActions from '../../../../actions/SegmentActions'
import {SEGMENTS_STATUS} from '../../../../constants/Constants'
import {Select} from '../../../common/Select'
import Switch from '../../../common/Switch'
import {DataSampleDropdown} from './DataSampleDropdown'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../../common/Button/Button'
import ChevronLeft from '../../../../../img/icons/ChevronLeft'
import ChevronRight from '../../../../../img/icons/ChevronRight'

const MORE_FILTERS = [
  {value: 'ice', label: '101%'},
  {value: 'unlocked', label: 'Not 101%'},
  {value: 'modified_ice', label: 'Modified 101%'},
  {value: 'repetitions', label: 'Repetitions'},
  {value: 'mt', label: 'MT'},
  {value: 'matches', label: '100% Matches'},
  {value: 'fuzzies_75_84', label: 'Fuzzies 75-84'},
  {value: 'fuzzies_85_94', label: 'Fuzzies 85-94'},
  {value: 'fuzzies_95_99', label: 'Fuzzies 95-99'},
  {value: 'todo', label: 'To do'},
]

const DEFAULT_STATE = {
  selectedStatus: undefined,
  samplingType: undefined,
  samplingSize: 5,
  filtering: false,
  filteredCount: 0,
  segmentsArray: [],
  filtersEnabled: true,
  dataSampleEnabled: false,
  filterSubmitted: false,
  revisionNumber: null,
}

const SegmentsFilter = ({active}) => {
  const [selectedStatus, setSelectedStatus] = useState(DEFAULT_STATE.selectedStatus)
  const [samplingType, setSamplingType] = useState(DEFAULT_STATE.samplingType)
  const [samplingSize, setSamplingSize] = useState(DEFAULT_STATE.samplingSize)
  const [filtering, setFiltering] = useState(DEFAULT_STATE.filtering)
  const [filteredCount, setFilteredCount] = useState(DEFAULT_STATE.filteredCount)
  const [segmentsArray, setSegmentsArray] = useState(DEFAULT_STATE.segmentsArray)
  const [dataSampleEnabled, setDataSampleEnabled] = useState(DEFAULT_STATE.dataSampleEnabled)
  const [filterSubmitted, setFilterSubmitted] = useState(DEFAULT_STATE.filterSubmitted)
  const [revisionNumber, setRevisionNumber] = useState(DEFAULT_STATE.revisionNumber)

  const filterWrapperRef = useRef(null)
  const stateRef = useRef({selectedStatus, samplingType, samplingSize, dataSampleEnabled, revisionNumber})

  useEffect(() => {
    stateRef.current = {selectedStatus, samplingType, samplingSize, dataSampleEnabled, revisionNumber}
  }, [selectedStatus, samplingType, samplingSize, dataSampleEnabled, revisionNumber])

  const resetState = useCallback(() => {
    setSelectedStatus(DEFAULT_STATE.selectedStatus)
    setSamplingType(DEFAULT_STATE.samplingType)
    setSamplingSize(DEFAULT_STATE.samplingSize)
    setFiltering(DEFAULT_STATE.filtering)
    setFilteredCount(DEFAULT_STATE.filteredCount)
    setSegmentsArray(DEFAULT_STATE.segmentsArray)
    setDataSampleEnabled(DEFAULT_STATE.dataSampleEnabled)
    setFilterSubmitted(DEFAULT_STATE.filterSubmitted)
    setRevisionNumber(DEFAULT_STATE.revisionNumber)
  }, [])

  const doSubmitFilter = useCallback(() => {
    const {selectedStatus: status, samplingType: type, samplingSize: size, dataSampleEnabled: sampleEnabled, revisionNumber: revNum} = stateRef.current
    let sample
    if (type) {
      sample = sampleEnabled ? {type, size} : {type}
    }
    if (sample || status) {
      SegmentFilterUtils.filterSubmit(
        {status, sample, revision_number: revNum},
        {samplingType: type, samplingSize: size, selectedStatus: status, dataSampleEnabled: sampleEnabled},
      )
      setFilterSubmitted(true)
    } else {
      setFiltering(false)
      setTimeout(() => SegmentFilterUtils.clearFilter())
    }
  }, [])

  const setFilter = useCallback((data, state) => {
    if (isUndefined(state)) {
      setFilteredCount(data.count)
      setFiltering(true)
      setSegmentsArray(data.segment_ids)
      setFilterSubmitted(false)
    } else {
      setFilteredCount(data.count)
      setFiltering(true)
      setSegmentsArray(data.segment_ids)
      setFilterSubmitted(false)
      if (state.selectedStatus !== undefined) setSelectedStatus(state.selectedStatus)
      if (state.samplingType !== undefined) setSamplingType(state.samplingType)
      if (state.samplingSize !== undefined) setSamplingSize(state.samplingSize)
      if (state.dataSampleEnabled !== undefined) setDataSampleEnabled(state.dataSampleEnabled)
      setTimeout(doSubmitFilter, 100)
    }
  }, [doSubmitFilter])

  const filterSegmentsError = useCallback(() => {
    setFilterSubmitted(false)
  }, [])

  useEffect(() => {
    SegmentFilterUtils.initEvents()
    const segmentFilterData = SegmentFilterUtils.getStoredState()
    if (SegmentFilterUtils.enabled() && segmentFilterData.reactState && segmentFilterData.open) {
      SegmentFilterUtils.openFilter()
    }
    CatToolStore.addListener(CatToolConstants.SET_SEGMENT_FILTER, setFilter)
    CatToolStore.addListener(CatToolConstants.SEGMENT_FILTER_ERROR, filterSegmentsError)
    CatToolStore.addListener(CatToolConstants.RELOAD_SEGMENT_FILTER, doSubmitFilter)
    return () => {
      CatToolStore.removeListener(CatToolConstants.SET_SEGMENT_FILTER, setFilter)
      CatToolStore.removeListener(CatToolConstants.SEGMENT_FILTER_ERROR, filterSegmentsError)
      CatToolStore.removeListener(CatToolConstants.RELOAD_SEGMENT_FILTER, doSubmitFilter)
    }
  }, [setFilter, filterSegmentsError, doSubmitFilter])

  useEffect(() => {
    if (filterWrapperRef.current) {
      const actionFilter = document.getElementById('action-filter')
      if (actionFilter) {
        actionFilter.classList.toggle('open', active)
      }
    }
  }, [active])

  const handleFilterSelectChanged = useCallback((value) => {
    let revNum = null
    if (value === 'APPROVED-2') {
      revNum = 2
      value = SEGMENTS_STATUS.APPROVED2
    }

    const isTodoConflict =
      (!config.isReview && value === 'TRANSLATED' && stateRef.current.samplingType === 'todo') ||
      (config.isReview && value === 'APPROVED' && stateRef.current.samplingType === 'todo')

    if (isTodoConflict) {
      setSamplingType(undefined)
    }

    setSelectedStatus(value)
    setRevisionNumber(revNum)
    setTimeout(doSubmitFilter, 100)
  }, [doSubmitFilter])

  const handleMoreFilterSelectChanged = useCallback((value) => {
    const isTodoConflict =
      (!config.isReview && stateRef.current.selectedStatus === 'TRANSLATED' && value === 'todo') ||
      (config.isReview && stateRef.current.selectedStatus === 'APPROVED' && value === 'todo')

    if (isTodoConflict) {
      setSelectedStatus('')
    }

    setSamplingType(value)
    setTimeout(doSubmitFilter, 100)
  }, [doSubmitFilter])

  const handleDataSampleChange = useCallback((value) => {
    setSamplingType(value)
    setTimeout(doSubmitFilter, 100)
  }, [doSubmitFilter])

  const handleClearClick = useCallback((e) => {
    e.preventDefault()
    SegmentFilterUtils.clearFilter()
    resetState()
  }, [resetState])

  const handleToggleChange = useCallback((checked) => {
    setDataSampleEnabled(checked)
    setSamplingType(undefined)
  }, [])

  const handleMoveUp = useCallback(() => {
    if (filtering && filteredCount > 1) {
      SegmentFilterUtils.gotoPreviousSegment()
    }
  }, [filtering, filteredCount])

  const handleMoveDown = useCallback(() => {
    if (filtering && filteredCount > 1) {
      SegmentActions.gotoNextSegment()
    }
  }, [filtering, filteredCount])

  const handleSelectAll = useCallback((event) => {
    event.stopPropagation()
    SegmentActions.setBulkSelectionSegments(segmentsArray.slice(0))
  }, [segmentsArray])

  const handleUnlockAll = useCallback((event) => {
    event.stopPropagation()
    SegmentActions.unlockSegments(segmentsArray.slice(0))
  }, [segmentsArray])

  if (!active) return null

  const statusOptions = config.searchable_statuses.map((item) => ({
    name: (
      <>
        <div className={'status-dot ' + item.label.toLowerCase() + '-color'} />
        {item.label}
      </>
    ),
    id: item.value,
  }))

  if (config.secondRevisionsCount) {
    statusOptions.push({
      name: (
        <>
          <div className={'status-dot approved-2ndpass-color'} />
          APPROVED 2
        </>
      ),
      id: 'APPROVED-2',
    })
  }

  const moreOptions = MORE_FILTERS.map((item) => ({
    name: item.label,
    id: item.value,
  }))

  const buttonArrowsClass = filtering && filteredCount > 1 ? 'qa-arrows-enabled' : 'qa-arrows-disbled'
  const statusFilterClass = selectedStatus !== '' ? 'filtered' : 'not-filtered'
  const filterClassEnabled = dataSampleEnabled
    ? 'disabled'
    : samplingType
      ? 'filtered'
      : 'not-filtered'

  return (
    <div className="filter-wrapper" ref={filterWrapperRef}>
      <div className="filter-container">
        <div className="filter-container-inside">
          <div className="filter-list">
            <div className="filter-dropdown">
              <Select
                className={'filter-status ' + statusFilterClass}
                options={statusOptions}
                onSelect={(value) => handleFilterSelectChanged(value.id)}
                activeOption={statusOptions.find((item) => item.id === selectedStatus) || undefined}
                placeholder="Segment status"
                checkSpaceToReverse={false}
                showResetButton={true}
                resetFunction={() => handleFilterSelectChanged()}
                maxHeightDroplist={200}
              />
            </div>
            <div className="filter-dropdown">
              <Select
                className={'filter-activities ' + filterClassEnabled}
                options={moreOptions}
                onSelect={(value) => handleMoreFilterSelectChanged(value.id)}
                activeOption={moreOptions.find((item) => item.id === samplingType) || undefined}
                placeholder="Others"
                checkSpaceToReverse={false}
                showResetButton={true}
                resetFunction={() => handleMoreFilterSelectChanged()}
                maxHeightDroplist={400}
                enabled={!dataSampleEnabled}
              />
            </div>

            {config.isReview && (
              <div className="filter-dropdown">
                <Switch
                  onChange={handleToggleChange}
                  active={dataSampleEnabled}
                  showText={false}
                />
                <DataSampleDropdown
                  onChange={handleDataSampleChange}
                  isDisabled={!dataSampleEnabled}
                  onChangeSampleSize={setSamplingSize}
                  samplingSize={samplingSize}
                  samplingType={samplingType}
                  resetFunction={() => {
                    setSamplingType(undefined)
                    setSamplingSize(5)
                  }}
                />
              </div>
            )}

            {filtering && (
              <div className="clear-filter-element">
                <div className="clear-filter">
                  <button onClick={handleClearClick}>Clear all filters</button>
                </div>
                {filteredCount > 0 && (
                  <div className="select-all-filter">
                    <button onClick={handleSelectAll}>Select all filtered segments</button>
                  </div>
                )}
                {filteredCount > 0 && samplingType === 'ice' && (
                  <div className="select-all-filter">
                    <button onClick={handleUnlockAll}>Unlock all filtered segments</button>
                  </div>
                )}
              </div>
            )}
          </div>

          <div className="filter-navigator">
            <div className="filter-actions">
              {filtering && filteredCount > 0 && !filterSubmitted && (
                <div className={'filter-arrows filter-arrows-enabled ' + buttonArrowsClass}>
                  <div className="label-filters labl">
                    <b>{filteredCount}</b> Filtered segments
                  </div>
                  <Button size={BUTTON_SIZE.ICON_STANDARD} mode={BUTTON_MODE.OUTLINE} onClick={handleMoveUp}>
                    <ChevronLeft />
                  </Button>
                  <Button onClick={handleMoveDown} mode={BUTTON_MODE.OUTLINE} size={BUTTON_SIZE.ICON_STANDARD}>
                    <ChevronRight />
                  </Button>
                </div>
              )}
              {filtering && !filterSubmitted && filteredCount === 0 && (
                <div className={'filter-arrows filter-arrows-enabled ' + buttonArrowsClass}>
                  <div className="label-filters labl">No segments found</div>
                </div>
              )}
              {filterSubmitted && (
                <div className="label-filters labl">
                  Applying filter
                  <div className="loader"></div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export default SegmentsFilter
