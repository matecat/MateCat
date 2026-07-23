import {render} from '@testing-library/react'
import {screen} from '@testing-library/dom'
import React from 'react'
import {ActivityLogTable} from './ActivityLogTable'
import {ActivityLogContext} from './ActivityLogContext'

const renderWithContext = (value) =>
  render(
    <ActivityLogContext.Provider value={value}>
      <ActivityLogTable />
    </ActivityLogContext.Provider>,
  )

const makeLog = (overrides = {}) => ({
  id: '1',
  ip: '127.0.0.1',
  event_date: '2024-01-15T10:30:00Z',
  id_project: '10',
  id_job: '20',
  languagePair: 'en-US - it-IT',
  userName: 'John Doe',
  email: 'john@example.com',
  action: 'open',
  ...overrides,
})

test('renders the column headers', () => {
  renderWithContext({
    activityLog: [],
    filterByColumn: {id: 'email', query: ''},
  })

  expect(screen.getByText('User IP')).toBeInTheDocument()
  expect(screen.getByText('Event Date')).toBeInTheDocument()
  expect(screen.getByText('Project ID')).toBeInTheDocument()
  expect(screen.getByText('Job ID')).toBeInTheDocument()
  expect(screen.getByText('Language Pair')).toBeInTheDocument()
  expect(screen.getByText('User Name')).toBeInTheDocument()
  expect(screen.getByText('User Email')).toBeInTheDocument()
  expect(screen.getByText('Action')).toBeInTheDocument()
})

test('renders a row per activity log entry that matches the filter', () => {
  renderWithContext({
    activityLog: [
      makeLog({id: '1', ip: '127.0.0.1'}),
      makeLog({id: '2', ip: '192.168.0.1'}),
    ],
    filterByColumn: {id: 'ip', query: ''},
  })

  expect(screen.getByText('127.0.0.1')).toBeInTheDocument()
  expect(screen.getByText('192.168.0.1')).toBeInTheDocument()
  expect(screen.queryByText('No records')).not.toBeInTheDocument()
})

test('formats the event date using the local date/time representation', () => {
  const log = makeLog({event_date: '2024-01-15T10:30:00Z'})
  const expected = new Date(log.event_date)
  const expectedText = `${expected.toDateString()} ${expected.toLocaleTimeString()}`

  renderWithContext({
    activityLog: [log],
    filterByColumn: {id: 'ip', query: ''},
  })

  expect(screen.getByText(expectedText)).toBeInTheDocument()
})

test('filters out entries whose column value does not match the query', () => {
  renderWithContext({
    activityLog: [
      makeLog({id: '1', ip: '127.0.0.1'}),
      makeLog({id: '2', ip: '192.168.0.1'}),
    ],
    filterByColumn: {id: 'ip', query: '^127'},
  })

  expect(screen.getByText('127.0.0.1')).toBeInTheDocument()
  expect(screen.queryByText('192.168.0.1')).not.toBeInTheDocument()
})

test('renders the empty state when no entry matches the filter', () => {
  renderWithContext({
    activityLog: [makeLog({id: '1', ip: '127.0.0.1'})],
    filterByColumn: {id: 'ip', query: 'no-match-at-all'},
  })

  expect(screen.getByText('No records')).toBeInTheDocument()
})

test('renders the empty state when there are no log entries at all', () => {
  renderWithContext({
    activityLog: [],
    filterByColumn: {id: 'ip', query: ''},
  })

  expect(screen.getByText('No records')).toBeInTheDocument()
})
