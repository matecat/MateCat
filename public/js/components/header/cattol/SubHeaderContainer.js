import React, {useCallback, useEffect, useState} from 'react'

import CatToolConstants from '../../../constants/CatToolConstants'
import CatToolStore from '../../../stores/CatToolStore'
import SegmentSelectionPanel from './bulk_selection_bar/BulkSelectionBar'
import SegmentsFilter from './segment_filter/SegmentsFilter'
import Search from './search/Search'
import QaComponent from './QAComponent'

const SubHeaderContainer = ({filtersEnabled, userInfo}) => {
  const [state, setState] = useState({
    selectionBar: true,
    search: false,
    segmentFilter: false,
    qaComponent: false,
  })

  const showContainer = useCallback((container) => {
    switch (container) {
      case 'search':
        setState((prev) => ({
          ...prev,
          search: true,
          segmentFilter: false,
          qaComponent: false,
        }))
        break
      case 'segmentFilter':
        setState((prev) => ({
          ...prev,
          search: false,
          segmentFilter: true,
          qaComponent: false,
        }))
        break
      case 'qaComponent':
        setState((prev) => ({
          ...prev,
          search: false,
          segmentFilter: false,
          qaComponent: true,
        }))
        break
    }
  }, [])

  const toggleContainer = useCallback((container) => {
    switch (container) {
      case 'search':
        setState((prev) => ({
          ...prev,
          search: !prev.search,
          segmentFilter: false,
          qaComponent: false,
        }))
        break
      case 'segmentFilter':
        setState((prev) => ({
          ...prev,
          search: false,
          segmentFilter: !prev.segmentFilter,
          qaComponent: false,
        }))
        break
      case 'qaComponent':
        setState((prev) => ({
          ...prev,
          search: false,
          segmentFilter: false,
          qaComponent: !prev.qaComponent,
        }))
        break
    }
  }, [])

  const closeSubHeader = useCallback(() => {
    setState((prev) => ({
      ...prev,
      search: false,
      segmentFilter: false,
      qaComponent: false,
    }))
  }, [])

  useEffect(() => {
    CatToolStore.addListener(CatToolConstants.SHOW_CONTAINER, showContainer)
    CatToolStore.addListener(CatToolConstants.TOGGLE_CONTAINER, toggleContainer)
    CatToolStore.addListener(CatToolConstants.CLOSE_SUBHEADER, closeSubHeader)

    return () => {
      CatToolStore.removeListener(
        CatToolConstants.SHOW_CONTAINER,
        showContainer,
      )
      CatToolStore.removeListener(
        CatToolConstants.TOGGLE_CONTAINER,
        toggleContainer,
      )
      CatToolStore.removeListener(
        CatToolConstants.CLOSE_SUBHEADER,
        closeSubHeader,
      )
    }
  }, [])

  return (
    <div>
      <Search
        active={state.search}
        isReview={config.isReview}
        searchable_statuses={config.searchable_statuses}
        userInfo={userInfo}
      />
      {filtersEnabled ? (
        <SegmentsFilter
          active={state.segmentFilter}
          isReview={config.isReview}
        />
      ) : null}
      <QaComponent active={state.qaComponent} isReview={config.isReview} />
      <SegmentSelectionPanel
        active={state.selectionBar}
        isReview={config.isReview}
      />
    </div>
  )
}

export default SubHeaderContainer
