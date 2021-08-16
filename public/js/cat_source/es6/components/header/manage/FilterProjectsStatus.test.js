import {render, screen} from '@testing-library/react'
import React from 'react'

import FilterProjectsStatus from './FilterProjectsStatus'

test('Rendering elements', () => {
  render(<FilterProjectsStatus filterFunction={() => {}} />)

  expect(screen.getAllByText('active').length).toBe(2)
  expect(screen.getByTestId('item-active')).toBeInTheDocument()
  expect(screen.getByTestId('item-archived')).toBeInTheDocument()
  expect(screen.getByTestId('item-cancelled')).toBeInTheDocument()
})
