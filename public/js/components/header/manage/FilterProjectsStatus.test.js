import {render, screen} from '@testing-library/react'
import React from 'react'
import userEvent from '@testing-library/user-event'

import FilterProjectsStatus from './FilterProjectsStatus'

class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

window.ResizeObserver = ResizeObserver

test('Rendering elements', async () => {
  render(<FilterProjectsStatus filterFunction={() => {}} />)

  await userEvent.click(screen.getByTestId('status-filter-trigger'))

  expect(screen.getByTestId('item-active')).toBeInTheDocument()
  expect(screen.getByTestId('item-archived')).toBeInTheDocument()
  expect(screen.getByTestId('item-cancelled')).toBeInTheDocument()
})

test('Selecting a status calls filterFunction and updates the trigger label', async () => {
  const filterFunction = jest.fn()
  render(<FilterProjectsStatus filterFunction={filterFunction} />)

  await userEvent.click(screen.getByTestId('status-filter-trigger'))
  await userEvent.click(screen.getByTestId('item-archived'))

  expect(filterFunction).toHaveBeenCalledWith('archived')
  expect(screen.getByTestId('status-filter-trigger')).toHaveTextContent(
    'Archived',
  )
})
