import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {DeleteResource} from './DeleteResource'

const setup = (props = {}) => {
  const onClose = jest.fn()
  const onConfirm = jest.fn()
  const utils = render(
    <DeleteResource
      row={{name: 'My MT engine'}}
      onClose={onClose}
      onConfirm={onConfirm}
      {...props}
    />,
  )
  return {onClose, onConfirm, ...utils}
}

describe('DeleteResource', () => {
  test('shows the MT confirmation message with the resource name by default', () => {
    setup()

    expect(screen.getByText('My MT engine')).toBeInTheDocument()
    expect(screen.getByText(/Do you really want to delete the/)).toHaveTextContent(
      'Do you really want to delete the MT: (My MT engine)',
    )
  })

  test('shows the Glossary confirmation message when type is glossary', () => {
    setup({type: 'glossary', row: {name: 'My Glossary'}})

    expect(screen.getByText(/Do you really want to delete the/)).toHaveTextContent(
      'Do you really want to delete the Glossary: (My Glossary)',
    )
  })

  test('clicking Confirm calls onConfirm', () => {
    const {onConfirm, onClose} = setup()

    fireEvent.click(screen.getByText('Confirm'))

    expect(onConfirm).toHaveBeenCalledTimes(1)
    expect(onClose).not.toHaveBeenCalled()
  })

  test('clicking the close button calls onClose', () => {
    const {onConfirm, onClose} = setup()

    // Confirm is the first button (labelled "Confirm"); the icon-only close
    // button, with no accessible name, is the second.
    const buttons = screen.getAllByRole('button')
    expect(buttons).toHaveLength(2)

    fireEvent.click(buttons[1])

    expect(onClose).toHaveBeenCalledTimes(1)
    expect(onConfirm).not.toHaveBeenCalled()
  })
})
