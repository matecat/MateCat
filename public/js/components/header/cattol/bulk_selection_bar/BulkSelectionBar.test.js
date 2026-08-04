import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import BulkSelectionBar from './BulkSelectionBar'
import SegmentActions from '../../../../actions/SegmentActions'
import SegmentStore from '../../../../stores/SegmentStore'
import SegmentConstants from '../../../../constants/SegmentConstants'
import CatToolActions from '../../../../actions/CatToolActions'

jest.mock('../../../../actions/SegmentActions')
jest.mock('../../../../actions/CatToolActions')

beforeEach(() => {
  global.config = {}
  jest.clearAllMocks()
})

const setSegments = (segments) =>
  act(() => {
    SegmentStore.emit(SegmentConstants.SET_BULK_SELECTION_SEGMENTS, segments)
  })

test('renders nothing when there is no bulk selection', () => {
  const {container} = render(<BulkSelectionBar isReview={false} />)
  expect(container.firstChild).toBeNull()
})

test('shows the singular label for a single selected segment', () => {
  render(<BulkSelectionBar isReview={false} />)
  setSegments([1])
  expect(screen.getByText('1 Segment selected')).toBeInTheDocument()
})

test('shows the plural label and translates the filtered segments when marking as translated', async () => {
  SegmentActions.translateFilteredSegments.mockResolvedValue()
  render(<BulkSelectionBar isReview={false} />)
  setSegments([1, 2])

  expect(screen.getByText('2 Segments selected')).toBeInTheDocument()

  await act(async () => {
    fireEvent.click(screen.getByText('MARK AS TRANSLATED'))
  })

  expect(SegmentActions.translateFilteredSegments).toHaveBeenCalledWith([1, 2])
  expect(CatToolActions.onRender).toHaveBeenCalledWith({segmentToOpen: 1})
  expect(SegmentActions.removeSegmentsOnBulk).toHaveBeenCalledTimes(1)
})

test('approves the filtered segments and reloads the quality report when reviewing', async () => {
  SegmentActions.approveFilteredSegments.mockResolvedValue()
  render(<BulkSelectionBar isReview={true} />)
  setSegments([5, 6])

  await act(async () => {
    fireEvent.click(screen.getByText('MARK AS APPROVED'))
  })

  expect(SegmentActions.approveFilteredSegments).toHaveBeenCalledWith([5, 6])
  expect(CatToolActions.onRender).toHaveBeenCalledWith({segmentToOpen: 5})
  expect(CatToolActions.reloadQualityReport).toHaveBeenCalledTimes(1)
  expect(SegmentActions.removeSegmentsOnBulk).toHaveBeenCalledTimes(1)
})

test('shows a loading state while the status change is in flight', async () => {
  let resolveApprove
  SegmentActions.approveFilteredSegments.mockReturnValue(
    new Promise((resolve) => {
      resolveApprove = resolve
    }),
  )
  render(<BulkSelectionBar isReview={true} />)
  setSegments([1])

  fireEvent.click(screen.getByText('MARK AS APPROVED'))

  expect(screen.getByText('Applying changes')).toBeInTheDocument()
  await act(async () => resolveApprove())
})

test('clears the selection when the back button is clicked', () => {
  render(<BulkSelectionBar isReview={false} />)
  setSegments([1, 2, 3])

  fireEvent.click(screen.getByText('back'))

  expect(SegmentActions.removeSegmentsOnBulk).toHaveBeenCalledTimes(1)
})

test('resets the selection when the store reports it was removed', () => {
  render(<BulkSelectionBar isReview={false} />)
  setSegments([1, 2, 3])
  expect(screen.getByText('3 Segments selected')).toBeInTheDocument()

  act(() => {
    SegmentStore.emit(SegmentConstants.REMOVE_SEGMENTS_ON_BULK)
  })

  expect(screen.queryByText(/Segments selected/)).toBeNull()
})

test('toggles a single segment in and out of the bulk selection', () => {
  render(<BulkSelectionBar isReview={false} />)
  setSegments([1, 2])

  act(() => {
    SegmentStore.emit(SegmentConstants.TOGGLE_SEGMENT_ON_BULK, 1)
  })
  expect(screen.getByText('1 Segment selected')).toBeInTheDocument()

  act(() => {
    SegmentStore.emit(SegmentConstants.TOGGLE_SEGMENT_ON_BULK, 1)
  })
  expect(screen.getByText('2 Segments selected')).toBeInTheDocument()
})
