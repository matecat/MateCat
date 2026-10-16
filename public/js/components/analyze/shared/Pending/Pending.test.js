import React from 'react'
import {render, screen} from '@testing-library/react'
import {Pending, PENDING_ALIGN} from './Pending'

jest.mock('./Pending.module.scss', () => ({
  __esModule: true,
  default: {pending: 'pending', end: 'end'},
}))

test('renders the children when the value is known', () => {
  render(
    <Pending known={true}>
      <span>1,234</span>
    </Pending>,
  )
  expect(screen.getByText('1,234')).toBeInTheDocument()
  expect(screen.queryByText('Not available yet')).not.toBeInTheDocument()
})

test('renders a placeholder with screen reader text when not known', () => {
  render(
    <Pending known={false} align={PENDING_ALIGN.END}>
      <span>1,234</span>
    </Pending>,
  )
  expect(screen.queryByText('1,234')).not.toBeInTheDocument()
  const placeholder = screen.getByTestId('pending')
  expect(placeholder).toHaveClass('pending', 'end')
  // Only the hidden text is exposed: the grey bar is aria-hidden
  expect(placeholder).toHaveTextContent(/^Not available yet$/)
  expect(screen.getByText('Not available yet')).toBeInTheDocument()
})
