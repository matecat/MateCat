import {fireEvent, render} from '@testing-library/react'
import {screen} from '@testing-library/dom'
import React, {useRef, useState} from 'react'
import {ColumnSorting} from './ColumnSorting'
import {ActivityLogContext} from './ActivityLogContext'

// Mirrors how ActivityLogTable owns currentSortingColumnId/onSorting for its
// ColumnSorting children, so clicking through the real state-driven lifecycle
// (unsorted -> desc -> asc -> unsorted) behaves as it would in the real app.
const Harness = (props) => {
  const [currentSortingColumnId, setCurrentSortingColumnId] = useState()
  return (
    <ColumnSorting
      {...props}
      currentSortingColumnId={currentSortingColumnId}
      onSorting={setCurrentSortingColumnId}
    />
  )
}

const renderWithContext = (contextValue, props) =>
  render(
    <ActivityLogContext.Provider value={contextValue}>
      <Harness {...props} />
    </ActivityLogContext.Provider>,
  )

// In the real app `setActivityLog` is the page-level state setter: calling it
// re-renders the whole ActivityLogTable subtree (and therefore ColumnSorting)
// even when currentSortingColumnId doesn't change between clicks (e.g.
// clicking the same already-active column a second/third time). This harness
// reproduces that by holding a real activityLog state and forwarding computed
// results to `spy` for assertions, instead of a no-op jest.fn() which would
// let React bail out of re-rendering and freeze the visible sort state.
const ToggleHarness = ({unordered, spy, ...props}) => {
  const [currentSortingColumnId, setCurrentSortingColumnId] = useState()
  const activityLogWithoutOrdering = useRef(unordered)
  const [, setActivityLogState] = useState(unordered)

  const setActivityLog = (updater) => {
    const result = updater(activityLogWithoutOrdering.current)
    spy(result)
    setActivityLogState(result)
  }

  return (
    <ActivityLogContext.Provider
      value={{activityLogWithoutOrdering, setActivityLog}}
    >
      <ColumnSorting
        {...props}
        currentSortingColumnId={currentSortingColumnId}
        onSorting={setCurrentSortingColumnId}
      />
    </ActivityLogContext.Provider>
  )
}

test('clicking a date column toggles desc -> asc -> unsorted and reorders the log', () => {
  const unordered = [
    {id: '1', event_date: '2024-01-01T00:00:00Z'},
    {id: '2', event_date: '2024-03-01T00:00:00Z'},
  ]
  const spy = jest.fn()

  const {container} = render(
    <ToggleHarness
      unordered={unordered}
      spy={spy}
      id="event_date"
      label="Event Date"
      sortingType="date"
    />,
  )

  const header = screen.getByText('Event Date')

  // first click -> desc (newest first)
  fireEvent.click(header)
  expect(spy).toHaveBeenCalledTimes(1)
  expect(spy.mock.calls[0][0].map((log) => log.id)).toEqual(['2', '1'])
  expect(header).not.toHaveClass('activity-table-column-order-asc')
  expect(container.querySelector('svg')).toBeInTheDocument()

  // second click -> asc (oldest first)
  fireEvent.click(header)
  expect(spy).toHaveBeenCalledTimes(2)
  expect(spy.mock.calls[1][0].map((log) => log.id)).toEqual(['1', '2'])
  expect(header).toHaveClass('activity-table-column-order-asc')
  expect(container.querySelector('svg')).toBeInTheDocument()

  // third click -> back to unsorted, original array returned
  fireEvent.click(header)
  expect(spy).toHaveBeenCalledTimes(3)
  expect(spy.mock.calls[2][0]).toBe(unordered)
  expect(header).not.toHaveClass('activity-table-column-order-asc')
  expect(container.querySelector('svg')).not.toBeInTheDocument()

  expect(header).toHaveClass('activity-table-column-order')
})

test('sorts a plain (non-date) column using raw value comparison', () => {
  // Input is already in ascending order ('a' then 'b'), so a correct
  // descending sort must actually reorder it (-> '2','1'). A no-op or
  // sign-flipped comparator would leave the input order untouched
  // (-> '1','2'), which is what makes this fixture discriminate.
  const unordered = [
    {id: '1', ip: 'a'},
    {id: '2', ip: 'b'},
  ]
  const setActivityLog = jest.fn()
  const contextValue = {
    activityLogWithoutOrdering: {current: unordered},
    setActivityLog,
  }

  renderWithContext(contextValue, {id: 'ip', label: 'User IP'})

  fireEvent.click(screen.getByText('User IP'))

  const sorted = setActivityLog.mock.calls[0][0]()
  expect(sorted.map((log) => log.id)).toEqual(['2', '1'])
})

test('resets to unsorted when another column becomes the current sorting column', () => {
  const unordered = [
    {id: '1', ip: 'b'},
    {id: '2', ip: 'a'},
  ]
  const setActivityLog = jest.fn()
  const contextValue = {
    activityLogWithoutOrdering: {current: unordered},
    setActivityLog,
  }

  render(
    <ActivityLogContext.Provider value={contextValue}>
      <ColumnSorting
        id="ip"
        label="User IP"
        sortingType={undefined}
        currentSortingColumnId="event_date"
        onSorting={jest.fn()}
      />
    </ActivityLogContext.Provider>,
  )

  const header = screen.getByText('User IP')
  expect(header).not.toHaveClass('activity-table-column-order-asc')

  fireEvent.click(header)

  // first click while column wasn't the active one still starts from unsorted -> desc
  const sorted = setActivityLog.mock.calls[0][0]()
  expect(sorted.map((log) => log.id)).toEqual(['1', '2'])
})
