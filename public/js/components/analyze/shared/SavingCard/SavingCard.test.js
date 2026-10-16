import React from 'react'
import {act, render, screen} from '@testing-library/react'
import {SavingCard} from './SavingCard'

// The shared CSS module mock maps the default import to a string: give the
// flash classes real names so the test can see them
jest.mock('../useValueFlash/useValueFlash.module.scss', () => ({
  __esModule: true,
  default: {flash: 'flash', flashing: 'flashing'},
}))

beforeEach(() => {
  jest.useFakeTimers()
})

afterEach(() => {
  jest.useRealTimers()
})

const props = {
  savingPercent: 25,
  workMinutesLabel: '2 work hours at 3,000 w/day',
  known: true,
  live: true,
}

test('shows the saving and the work time', () => {
  render(<SavingCard {...props} />)
  expect(
    screen.getByRole('heading', {name: 'Saving on word count'}),
  ).toBeInTheDocument()
  expect(screen.getByText('25%')).toBeInTheDocument()
  expect(screen.getByText('2 work hours at 3,000 w/day')).toBeInTheDocument()
  expect(screen.getByTestId('saving-card')).not.toHaveClass('flashing')
})

test('flashes when the saving changes while live', () => {
  const {rerender} = render(<SavingCard {...props} />)
  rerender(<SavingCard {...props} savingPercent={30} />)
  expect(screen.getByTestId('saving-card')).toHaveClass('flash', 'flashing')

  act(() => {
    jest.advanceTimersByTime(400)
  })
  expect(screen.getByTestId('saving-card')).not.toHaveClass('flashing')
})

test('does not flash when not live', () => {
  const {rerender} = render(<SavingCard {...props} live={false} />)
  rerender(<SavingCard {...props} live={false} savingPercent={30} />)
  expect(screen.getByText('30%')).toBeInTheDocument()
  expect(screen.getByTestId('saving-card')).not.toHaveClass('flashing')
})

test('shows placeholders when the saving is not known', () => {
  render(<SavingCard {...props} known={false} />)
  expect(screen.queryByText('25%')).not.toBeInTheDocument()
  expect(
    screen.queryByText('2 work hours at 3,000 w/day'),
  ).not.toBeInTheDocument()
  expect(screen.getAllByText('Not available yet')).toHaveLength(2)
})

test('does not flash while the saving is not known', () => {
  const {rerender} = render(<SavingCard {...props} known={false} />)
  rerender(<SavingCard {...props} known={false} savingPercent={30} />)
  expect(screen.getByTestId('saving-card')).not.toHaveClass('flashing')
})

test('shows a placeholder for a missing saving', () => {
  render(<SavingCard {...props} savingPercent={undefined} />)
  expect(screen.queryByText('undefined%')).not.toBeInTheDocument()
  expect(screen.getByText('Not available yet')).toBeInTheDocument()
  expect(screen.getByText('2 work hours at 3,000 w/day')).toBeInTheDocument()
})
