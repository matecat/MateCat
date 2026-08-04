import React from 'react'
import {render, screen, act, fireEvent} from '@testing-library/react'
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

// Lightweight Dropdown stub so the real Select component can be driven from
// tests without depending on its internal positioning/portal logic.
jest.mock('../../../common/Dropdown', () => {
  const {forwardRef, useImperativeHandle} = require('react')
  return {
    Dropdown: forwardRef(({options, onSelect}, ref) => {
      useImperativeHandle(ref, () => ({
        getListRef: () => ({
          getBoundingClientRect: () => ({top: 0, height: 0}),
        }),
        setListMaxHeight: jest.fn(),
      }))
      return (
        <div data-testid="dropdown">
          {options?.map((opt) => (
            <button
              key={opt.id}
              data-testid={`option-${opt.id}`}
              onClick={() => onSelect(opt)}
            >
              {opt.name}
            </button>
          ))}
        </div>
      )
    }),
  }
})

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
  expect(SegmentActions.setBulkSelectionSegments).toHaveBeenCalledWith([
    100, 200,
  ])
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

test('calls openFilter on mount when a stored open filter state exists', () => {
  SegmentFilterUtils.enabled.mockReturnValue(true)
  SegmentFilterUtils.getStoredState.mockReturnValue({
    reactState: {},
    open: true,
  })

  renderFilter()

  expect(SegmentFilterUtils.openFilter).toHaveBeenCalledTimes(1)
})

test('marks the action-filter element as open while the filter is active', () => {
  document.body.insertAdjacentHTML(
    'afterbegin',
    '<div id="action-filter"></div>',
  )

  renderFilter({active: true})
  expect(document.getElementById('action-filter')).toHaveClass('open')

  document.getElementById('action-filter').remove()
})

test('reload event clears the filter when no status or sample is selected', () => {
  jest.useFakeTimers()
  renderFilter()

  act(() => {
    CatToolStore.emit(CatToolConstants.RELOAD_SEGMENT_FILTER)
  })
  act(() => {
    jest.runOnlyPendingTimers()
  })

  expect(SegmentFilterUtils.clearFilter).toHaveBeenCalled()
  jest.useRealTimers()
})

test('setFilter with extended state applies it, submits, and enables the unlock action', () => {
  jest.useFakeTimers()
  renderFilter()

  act(() => {
    CatToolStore.emit(
      CatToolConstants.SET_SEGMENT_FILTER,
      {count: 2, segment_ids: [1, 2]},
      {
        selectedStatus: 'TRANSLATED',
        samplingType: 'ice',
        samplingSize: 10,
        dataSampleEnabled: true,
      },
    )
  })
  act(() => {
    jest.advanceTimersByTime(100)
  })

  expect(SegmentFilterUtils.filterSubmit).toHaveBeenCalledWith(
    {
      status: 'TRANSLATED',
      sample: {type: 'ice', size: 10},
      revision_number: null,
    },
    {
      samplingType: 'ice',
      samplingSize: 10,
      selectedStatus: 'TRANSLATED',
      dataSampleEnabled: true,
    },
  )
  expect(screen.getByText('Applying filter')).toBeInTheDocument()

  fireEvent.click(screen.getByText('Unlock all filtered segments'))
  expect(SegmentActions.unlockSegments).toHaveBeenCalledWith([1, 2])

  act(() => {
    CatToolStore.emit(CatToolConstants.SEGMENT_FILTER_ERROR)
  })
  expect(screen.queryByText('Applying filter')).not.toBeInTheDocument()

  jest.useRealTimers()
})

test('selecting a status submits the filter and the reset button clears it', () => {
  jest.useFakeTimers()
  renderFilter()

  fireEvent.click(document.querySelector('.filter-status .select'))
  fireEvent.click(screen.getByTestId('option-TRANSLATED'))
  act(() => {
    jest.advanceTimersByTime(100)
  })

  expect(SegmentFilterUtils.filterSubmit).toHaveBeenCalledWith(
    {status: 'TRANSLATED', sample: undefined, revision_number: null},
    {
      samplingType: undefined,
      samplingSize: 5,
      selectedStatus: 'TRANSLATED',
      dataSampleEnabled: false,
    },
  )
  expect(
    document.querySelector('.filter-status .icon-reset'),
  ).toBeInTheDocument()

  fireEvent.click(document.querySelector('.filter-status .icon-reset'))

  expect(
    document.querySelector('.filter-status .icon-reset'),
  ).not.toBeInTheDocument()

  jest.useRealTimers()
})

test('selecting APPROVED-2 submits the filter with revision number 2', () => {
  jest.useFakeTimers()
  config.secondRevisionsCount = true
  renderFilter()

  fireEvent.click(document.querySelector('.filter-status .select'))
  fireEvent.click(screen.getByTestId('option-APPROVED-2'))
  act(() => {
    jest.advanceTimersByTime(100)
  })

  expect(SegmentFilterUtils.filterSubmit).toHaveBeenCalledWith(
    expect.objectContaining({status: 'APPROVED2', revision_number: 2}),
    expect.anything(),
  )

  jest.useRealTimers()
})

test('choosing a status resets a conflicting "todo" sampling type', () => {
  jest.useFakeTimers()
  renderFilter()

  fireEvent.click(document.querySelector('.filter-activities .select'))
  fireEvent.click(screen.getByTestId('option-todo'))
  act(() => {
    jest.advanceTimersByTime(100)
  })

  fireEvent.click(document.querySelector('.filter-status .select'))
  fireEvent.click(screen.getByTestId('option-TRANSLATED'))
  act(() => {
    jest.advanceTimersByTime(100)
  })

  expect(SegmentFilterUtils.filterSubmit).toHaveBeenLastCalledWith(
    expect.objectContaining({status: 'TRANSLATED', sample: undefined}),
    expect.objectContaining({
      samplingType: undefined,
      selectedStatus: 'TRANSLATED',
    }),
  )

  jest.useRealTimers()
})

test('choosing "todo" again resets a conflicting TRANSLATED status', () => {
  jest.useFakeTimers()
  renderFilter()

  fireEvent.click(document.querySelector('.filter-activities .select'))
  fireEvent.click(screen.getByTestId('option-todo'))
  act(() => {
    jest.advanceTimersByTime(100)
  })

  fireEvent.click(document.querySelector('.filter-status .select'))
  fireEvent.click(screen.getByTestId('option-TRANSLATED'))
  act(() => {
    jest.advanceTimersByTime(100)
  })

  fireEvent.click(document.querySelector('.filter-activities .select'))
  fireEvent.click(screen.getByTestId('option-todo'))
  act(() => {
    jest.advanceTimersByTime(100)
  })

  expect(SegmentFilterUtils.filterSubmit).toHaveBeenLastCalledWith(
    expect.objectContaining({status: '', sample: {type: 'todo'}}),
    expect.objectContaining({selectedStatus: '', samplingType: 'todo'}),
  )

  jest.useRealTimers()
})

test('resetting the "others" filter clears the current filter', () => {
  jest.useFakeTimers()
  renderFilter()

  fireEvent.click(document.querySelector('.filter-activities .select'))
  fireEvent.click(screen.getByTestId('option-ice'))
  act(() => {
    jest.advanceTimersByTime(100)
  })
  expect(SegmentFilterUtils.filterSubmit).toHaveBeenCalledTimes(1)

  fireEvent.click(document.querySelector('.filter-activities .icon-reset'))
  act(() => {
    jest.advanceTimersByTime(100)
  })
  act(() => {
    jest.runOnlyPendingTimers()
  })

  expect(SegmentFilterUtils.filterSubmit).toHaveBeenCalledTimes(1)
  expect(SegmentFilterUtils.clearFilter).toHaveBeenCalled()

  jest.useRealTimers()
})

test('toggling the data sample switch checks it and calls the toggle handler', () => {
  config.isReview = true
  const {container} = renderFilter()
  const checkbox = container.querySelector('input[type="checkbox"]')

  fireEvent.click(checkbox)

  expect(checkbox).toBeChecked()
})

test('selecting a data sample type submits the filter with the sample type', () => {
  config.isReview = true
  jest.useFakeTimers()
  renderFilter()

  fireEvent.click(screen.getByText('Regular interval'))
  act(() => {
    jest.advanceTimersByTime(100)
  })

  expect(SegmentFilterUtils.filterSubmit).toHaveBeenCalledWith(
    expect.objectContaining({sample: {type: 'regular_intervals'}}),
    expect.objectContaining({samplingType: 'regular_intervals'}),
  )

  jest.useRealTimers()
})

test('move up/down arrows are inert with a single result but active with several', () => {
  renderFilter()

  act(() => {
    CatToolStore.emit(CatToolConstants.SET_SEGMENT_FILTER, {
      count: 1,
      segment_ids: [1],
    })
  })
  const [moveUpSingle, moveDownSingle] = document.querySelectorAll(
    '.filter-arrows button',
  )
  fireEvent.click(moveUpSingle)
  fireEvent.click(moveDownSingle)
  expect(SegmentFilterUtils.gotoPreviousSegment).not.toHaveBeenCalled()
  expect(SegmentActions.gotoNextSegment).not.toHaveBeenCalled()

  act(() => {
    CatToolStore.emit(CatToolConstants.SET_SEGMENT_FILTER, {
      count: 3,
      segment_ids: [1, 2, 3],
    })
  })
  const [moveUp, moveDown] = document.querySelectorAll('.filter-arrows button')
  fireEvent.click(moveUp)
  fireEvent.click(moveDown)
  expect(SegmentFilterUtils.gotoPreviousSegment).toHaveBeenCalledTimes(1)
  expect(SegmentActions.gotoNextSegment).toHaveBeenCalledTimes(1)
})
