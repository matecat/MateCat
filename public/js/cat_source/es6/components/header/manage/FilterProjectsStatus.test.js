import {render, screen} from '@testing-library/react'
import FilterProjectsStatus from './FilterProjectsStatus'
import React from 'react'
import ReactDOM from 'react-dom'

window.React = React
window.ReactDOM = ReactDOM

test('Rendering elements', () => {
  render(<FilterProjectsStatus filterFunction={() => {}} />)

  expect(screen.getAllByText('active').length).toBe(2)
  expect(screen.getByTestId('item-active')).toBeInTheDocument()
  expect(screen.getByTestId('item-archived')).toBeInTheDocument()
  expect(screen.getByTestId('item-cancelled')).toBeInTheDocument()
})
