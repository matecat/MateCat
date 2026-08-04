import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {SegmentsFilterButton} from './SegmentsFilterButton'
import CatToolStore from '../../../stores/CatToolStore'
import CatToolConstants from '../../../constants/CatToolConstants'
import SegmentFilter from './segment_filter/segment_filter'

jest.mock('./segment_filter/segment_filter', () => ({
  open: false,
  openFilter: jest.fn(),
  closeFilter: jest.fn(),
}))

beforeEach(() => {
  global.config = {segmentFilterEnabled: true}
  SegmentFilter.open = false
  jest.clearAllMocks()
})

test('renders nothing when the segment filter feature is disabled', () => {
  global.config.segmentFilterEnabled = false
  const {container} = render(<SegmentsFilterButton />)
  expect(container.firstChild).toBeNull()
})

test('opens the filter when clicked while closed', () => {
  render(<SegmentsFilterButton />)
  fireEvent.click(screen.getByRole('button'))
  expect(SegmentFilter.openFilter).toHaveBeenCalledTimes(1)
  expect(SegmentFilter.closeFilter).not.toHaveBeenCalled()
})

test('closes the filter and resets the open flag when clicked while open', () => {
  SegmentFilter.open = true
  render(<SegmentsFilterButton />)
  fireEvent.click(screen.getByRole('button'))
  expect(SegmentFilter.closeFilter).toHaveBeenCalledTimes(1)
  expect(SegmentFilter.openFilter).not.toHaveBeenCalled()
  expect(SegmentFilter.open).toBe(false)
})

test('toggles open state when the segmentFilter container is toggled from the store', () => {
  render(<SegmentsFilterButton />)
  act(() => {
    CatToolStore.emit(CatToolConstants.TOGGLE_CONTAINER, 'segmentFilter')
  })
  expect(screen.getByRole('button')).toBeInTheDocument()
  act(() => {
    CatToolStore.emit(CatToolConstants.TOGGLE_CONTAINER, 'segmentFilter')
  })
  expect(screen.getByRole('button')).toBeInTheDocument()
})

test('closes the filter icon when another container is shown or the subheader closes', () => {
  SegmentFilter.open = true
  render(<SegmentsFilterButton />)
  act(() => {
    CatToolStore.emit(CatToolConstants.SHOW_CONTAINER, 'search')
  })
  expect(screen.getByRole('button')).toBeInTheDocument()
  act(() => {
    CatToolStore.emit(CatToolConstants.CLOSE_SUBHEADER)
  })
  expect(screen.getByRole('button')).toBeInTheDocument()
})
