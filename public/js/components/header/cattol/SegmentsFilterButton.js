import React, {useEffect, useState} from 'react'
import SegmentFilter from './segment_filter/segment_filter'
import {Button, BUTTON_MODE, BUTTON_SIZE} from '../../common/Button/Button'
import CatToolStore from '../../../stores/CatToolStore'
import CatToolConstants from '../../../constants/CatToolConstants'
import FilterFilledIcon from '../../../../img/icons/FilterFilledIcon'
import FilterIcon from '../../../../img/icons/FilterIcon'

export const SegmentsFilterButton = () => {
  const [filterOpen, setFilterOpen] = useState(SegmentFilter.open)

  const openSegmetsFilters = (event) => {
    event.preventDefault()
    if (!SegmentFilter.open) {
      SegmentFilter.openFilter()
    } else {
      SegmentFilter.closeFilter()
      SegmentFilter.open = false
    }
  }
  useEffect(() => {
    const closeFilter = (container) => {
      if (container && container === 'segmentFilter') {
        setFilterOpen((prevState) => !prevState)
      } else {
        setFilterOpen(false)
      }
    }
    CatToolStore.addListener(CatToolConstants.TOGGLE_CONTAINER, closeFilter)
    CatToolStore.addListener(CatToolConstants.CLOSE_SUBHEADER, closeFilter)
    CatToolStore.addListener(CatToolConstants.SHOW_CONTAINER, closeFilter)
    return () => {
      CatToolStore.removeListener(
        CatToolConstants.TOGGLE_CONTAINER,
        closeFilter,
      )
      CatToolStore.removeListener(CatToolConstants.CLOSE_SUBHEADER, closeFilter)
      CatToolStore.removeListener(CatToolConstants.SHOW_CONTAINER, closeFilter)
    }
  }, [])
  return (
    <>
      {config.segmentFilterEnabled &&
        (filterOpen ? (
          <Button
            size={BUTTON_SIZE.ICON_STANDARD}
            mode={BUTTON_MODE.GHOST}
            onClick={openSegmetsFilters}
          >
            <FilterFilledIcon />
          </Button>
        ) : (
          <Button
            size={BUTTON_SIZE.ICON_STANDARD}
            mode={BUTTON_MODE.GHOST}
            onClick={openSegmetsFilters}
          >
            <FilterIcon />
          </Button>
        ))}
    </>
  )
}
