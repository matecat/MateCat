import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {Dropdown} from './Dropdown'

// Every row counts as truncated, so hovering one always asks for a tooltip.
jest.mock('../../utils/textUtils', () => ({
  isContentTextEllipsis: jest.fn(() => true),
}))

const renderDropdown = (options) =>
  render(<Dropdown wrapper={{current: document.body}} options={options} />)

describe('Dropdown row tooltip', () => {
  test('shows the name of a truncated row', () => {
    renderDropdown([{id: '1', name: 'TRANSLATED'}])

    fireEvent.mouseEnter(screen.getByRole('listitem'))

    expect(screen.getByLabelText('TRANSLATED')).toHaveClass('dropdown__tooltip')
  })

  test('shows the text of a row whose name is markup', () => {
    renderDropdown([
      {
        id: '1',
        name: (
          <>
            <div className="status-dot translated-color" />
            TRANSLATED
          </>
        ),
      },
    ])

    fireEvent.mouseEnter(screen.getByRole('listitem'))

    expect(screen.getByLabelText('TRANSLATED')).toHaveClass('dropdown__tooltip')
    expect(screen.queryByLabelText('[object Object]')).not.toBeInTheDocument()
  })

  test('hides the tooltip when the pointer leaves the row', () => {
    renderDropdown([{id: '1', name: 'TRANSLATED'}])
    const row = screen.getByRole('listitem')

    fireEvent.mouseEnter(row)
    fireEvent.mouseLeave(row)

    expect(screen.queryByLabelText('TRANSLATED')).not.toBeInTheDocument()
  })
})
