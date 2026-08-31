import {fireEvent, render} from '@testing-library/react'
import {screen} from '@testing-library/dom'
import React from 'react'
import {FilterColumn} from './FilterColumn'
import {ActivityLogContext} from './ActivityLogContext'

// Isolate FilterColumn's own onSelect/onChangeQuery handlers from Select's
// internal dropdown/portal rendering, which is covered by its own directory.
jest.mock('../common/Select', () => ({
  Select: ({label, options, onSelect}) => (
    <div>
      <span>{label}</span>
      {options.map((option) => (
        <button key={option.id} onClick={() => onSelect(option)}>
          {option.name}
        </button>
      ))}
      <button onClick={() => onSelect(undefined)}>clear selection</button>
    </div>
  ),
}))

const renderWithContext = (value) =>
  render(
    <ActivityLogContext.Provider value={value}>
      <FilterColumn />
    </ActivityLogContext.Provider>,
  )

test('renders the filter input using the label and query from context', () => {
  renderWithContext({
    filterByColumn: {id: 'ip', label: 'User IP', query: '127'},
    setFilterByColumn: jest.fn(),
  })

  const input = screen.getByPlaceholderText('Filter by User IP')
  expect(input).toHaveValue('127')
})

test('typing into the filter input updates the query via setFilterByColumn', () => {
  const setFilterByColumn = jest.fn()
  renderWithContext({
    filterByColumn: {id: 'email', label: 'User Email', query: ''},
    setFilterByColumn,
  })

  fireEvent.change(screen.getByPlaceholderText('Filter by User Email'), {
    target: {value: 'jane'},
  })

  expect(setFilterByColumn).toHaveBeenCalledTimes(1)
  const updater = setFilterByColumn.mock.calls[0][0]
  expect(updater({id: 'email', label: 'User Email', query: ''})).toEqual({
    id: 'email',
    label: 'User Email',
    query: 'jane',
  })
})

test('selecting a column from the Select updates id and label via setFilterByColumn', () => {
  const setFilterByColumn = jest.fn()
  renderWithContext({
    filterByColumn: {id: 'email', label: 'User Email', query: ''},
    setFilterByColumn,
  })

  fireEvent.click(screen.getByText('User IP'))

  expect(setFilterByColumn).toHaveBeenCalledTimes(1)
  const updater = setFilterByColumn.mock.calls[0][0]
  expect(updater({id: 'email', label: 'User Email', query: 'kept'})).toEqual({
    id: 'ip',
    label: 'User IP',
    query: 'kept',
  })
})

test('does nothing when Select reports no option', () => {
  const setFilterByColumn = jest.fn()
  renderWithContext({
    filterByColumn: {id: 'email', label: 'User Email', query: ''},
    setFilterByColumn,
  })

  fireEvent.click(screen.getByText('clear selection'))

  expect(setFilterByColumn).not.toHaveBeenCalled()
})
