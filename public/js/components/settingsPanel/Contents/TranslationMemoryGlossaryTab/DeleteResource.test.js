import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {DeleteResource} from './DeleteResource'

const row = {name: 'My Resource'}

test('shows a confirmation message naming the resource', () => {
  render(<DeleteResource row={row} onClose={jest.fn()} onConfirm={jest.fn()} />)

  expect(
    screen.getByText(/Do you really want to delete this resource/),
  ).toBeInTheDocument()
  expect(screen.getByText('My Resource')).toBeInTheDocument()
})

test('renders the optional footer content when provided', () => {
  render(
    <DeleteResource
      row={row}
      onClose={jest.fn()}
      onConfirm={jest.fn()}
      footerContent={<span>Used in 3 projects</span>}
    />,
  )

  expect(screen.getByText('Used in 3 projects')).toBeInTheDocument()
})

test('clicking Confirm calls onConfirm', () => {
  const onConfirm = jest.fn()
  render(<DeleteResource row={row} onClose={jest.fn()} onConfirm={onConfirm} />)

  fireEvent.click(screen.getByText('Confirm'))

  expect(onConfirm).toHaveBeenCalledTimes(1)
})

test('clicking the close icon button calls onClose', () => {
  const onClose = jest.fn()
  render(<DeleteResource row={row} onClose={onClose} onConfirm={jest.fn()} />)

  const buttons = screen.getAllByRole('button')
  fireEvent.click(buttons[buttons.length - 1])

  expect(onClose).toHaveBeenCalledTimes(1)
})
