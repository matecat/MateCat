import React from 'react'
import {render, screen, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'

import CatToolConstants from '../../../../constants/CatToolConstants'
import CatToolStore from '../../../../stores/CatToolStore'

jest.mock('./segment_filter', () => ({
  initEvents: jest.fn(),
  enabled: jest.fn(() => false),
  getStoredState: jest.fn(() => ({})),
  openFilter: jest.fn(),
  clearFilter: jest.fn(),
  filterSubmit: jest.fn(),
  gotoPreviousSegment: jest.fn(),
}))

jest.mock('../../../../actions/SegmentActions', () => ({
  gotoNextSegment: jest.fn(),
  setBulkSelectionSegments: jest.fn(),
  unlockSegments: jest.fn(),
}))

beforeEach(() => {
  jest.clearAllMocks()
  global.config = {
    isReview: false,
    secondRevisionsCount: false,
    segmentFilterEnabled: true,
    searchable_statuses: [
      {value: 'NEW', label: 'NEW'},
      {value: 'DRAFT', label: 'DRAFT'},
      {value: 'TRANSLATED', label: 'TRANSLATED'},
      {value: 'APPROVED', label: 'APPROVED'},
      {value: 'REJECTED', label: 'REJECTED'},
    ],
  }
})

const renderFilter = (props = {}) => {
  return render(<SegmentsFilter active={true} {...props} />)
}

// Lazy import so mocks are set up first
let SegmentsFilter, SegmentFilterUtils, SegmentActions
beforeAll(() => {
  SegmentsFilter = require('./SegmentsFilter').default
  SegmentFilterUtils = require('./segment_filter')
  SegmentActions = require('../../../../actions/SegmentActions')
})

test('renders nothing when not active', () => {
  const {container} = render(<SegmentsFilter active={false} />)
  expect(container.innerHTML).toBe('')
})

test('renders filter UI when active', () => {
  renderFilter()
  expect(document.querySelector('.filter-wrapper')).toBeInTheDocument()
  expect(document.querySelector('.filter-container')).toBeInTheDocument()
})

test('calls initEvents on mount', () => {
  renderFilter()
  expect(SegmentFilterUtils.initEvents).toHaveBeenCalledTimes(1)
})

test('registers and unregisters store listeners', () => {
  const addSpy = jest.spyOn(CatToolStore, 'addListener')
  const removeSpy = jest.spyOn(CatToolStore, 'removeListener')

  const {unmount} = renderFilter()

  expect(addSpy).toHaveBeenCalledWith(
    CatToolConstants.SET_SEGMENT_FILTER,
    expect.any(Function),
  )
  expect(addSpy).toHaveBeenCalledWith(
    CatToolConstants.SEGMENT_FILTER_ERROR,
    expect.any(Function),
  )
  expect(addSpy).toHaveBeenCalledWith(
    CatToolConstants.RELOAD_SEGMENT_FILTER,
    expect.any(Function),
  )

  unmount()

  expect(removeSpy).toHaveBeenCalledWith(
    CatToolConstants.SET_SEGMENT_FILTER,
    expect.any(Function),
  )

  addSpy.mockRestore()
  removeSpy.mockRestore()
})

test('shows clear button after filter is applied via store event', () => {
  renderFilter()

  act(() => {
    CatToolStore.emit(CatToolConstants.SET_SEGMENT_FILTER, {
      count: 5,
      segment_ids: [1, 2, 3, 4, 5],
    })
  })

  expect(screen.getByText('Clear all filters')).toBeInTheDocument()
})

test('clear button calls clearFilter and resets state', async () => {
  const user = userEvent.setup()
  renderFilter()

  act(() => {
    CatToolStore.emit(CatToolConstants.SET_SEGMENT_FILTER, {
      count: 3,
      segment_ids: [1, 2, 3],
    })
  })

  await user.click(screen.getByText('Clear all filters'))
  expect(SegmentFilterUtils.clearFilter).toHaveBeenCalled()
})

test('shows select all button when filtered count > 0', () => {
  renderFilter()

  act(() => {
    CatToolStore.emit(CatToolConstants.SET_SEGMENT_FILTER, {
      count: 3,
      segment_ids: [10, 20, 30],
    })
  })

  expect(screen.getByText('Select all filtered segments')).toBeInTheDocument()
})

test('select all calls setBulkSelectionSegments with segment ids', async () => {
  const user = userEvent.setup()
  renderFilter()

  act(() => {
    CatToolStore.emit(CatToolConstants.SET_SEGMENT_FILTER, {
      count: 2,
      segment_ids: [100, 200],
    })
  })

  await user.click(screen.getByText('Select all filtered segments'))
  expect(SegmentActions.setBulkSelectionSegments).toHaveBeenCalledWith([100, 200])
})

test('shows navigation arrows when filtered count > 1', () => {
  renderFilter()

  act(() => {
    CatToolStore.emit(CatToolConstants.SET_SEGMENT_FILTER, {
      count: 5,
      segment_ids: [1, 2, 3, 4, 5],
    })
  })

  expect(screen.getByText('Filtered segments')).toBeInTheDocument()
})

test('shows "No segments found" when filtered count is 0', () => {
  renderFilter()

  act(() => {
    CatToolStore.emit(CatToolConstants.SET_SEGMENT_FILTER, {
      count: 0,
      segment_ids: [],
    })
  })

  expect(screen.getByText('No segments found')).toBeInTheDocument()
})

test('shows data sample toggle only in review mode', () => {
  config.isReview = false
  const {container, rerender} = renderFilter()
  expect(container.querySelectorAll('input[type="checkbox"]').length).toBe(0)

  config.isReview = true
  rerender(<SegmentsFilter active={true} />)
  expect(container.querySelector('input[type="checkbox"]')).toBeInTheDocument()
})
