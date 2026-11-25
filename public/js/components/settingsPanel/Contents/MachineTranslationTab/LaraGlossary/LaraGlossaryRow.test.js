import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {LaraGlossaryRow} from './LaraGlossaryRow'

const setup = ({
  row = {id: 'lara-1', name: 'Lara glossary', isActive: false},
  isReadOnly = false,
} = {}) => {
  const setRows = jest.fn()
  const utils = render(
    <LaraGlossaryRow row={row} setRows={setRows} isReadOnly={isReadOnly} />,
  )
  return {setRows, row, ...utils}
}

describe('LaraGlossaryRow', () => {
  test('renders the glossary name', () => {
    setup({row: {id: 'lara-1', name: 'Lara glossary', isActive: false}})

    expect(screen.getByDisplayValue('Lara glossary')).toBeInTheDocument()
  })

  test('renders the checkbox unchecked when row is not active', () => {
    setup({row: {id: 'lara-1', name: 'Lara glossary', isActive: false}})

    expect(screen.getByTestId('laraglossary-active-lara-1')).not.toBeChecked()
  })

  test('renders the checkbox checked when row is active', () => {
    setup({row: {id: 'lara-1', name: 'Lara glossary', isActive: true}})

    expect(screen.getByTestId('laraglossary-active-lara-1')).toBeChecked()
  })

  test('syncs checked state when row.isActive prop changes', () => {
    const row = {id: 'lara-1', name: 'Lara glossary', isActive: false}
    const {rerender} = render(
      <LaraGlossaryRow row={row} setRows={jest.fn()} isReadOnly={false} />,
    )

    expect(screen.getByTestId('laraglossary-active-lara-1')).not.toBeChecked()

    rerender(
      <LaraGlossaryRow
        row={{...row, isActive: true}}
        setRows={jest.fn()}
        isReadOnly={false}
      />,
    )

    expect(screen.getByTestId('laraglossary-active-lara-1')).toBeChecked()
  })

  test('updates this row active state on change', () => {
    const {setRows} = setup({
      row: {id: 'lara-1', name: 'Lara glossary', isActive: false},
    })

    fireEvent.click(screen.getByTestId('laraglossary-active-lara-1'))

    expect(setRows).toHaveBeenCalledWith(expect.any(Function))

    const updater = setRows.mock.calls[0][0]
    const result = updater([
      {id: 'lara-1', isActive: false},
      {id: 'lara-2', isActive: true},
    ])

    expect(result).toEqual([
      {id: 'lara-1', isActive: true},
      {id: 'lara-2', isActive: true},
    ])
  })

  test('disables the checkbox when read-only', () => {
    setup({
      row: {id: 'lara-1', name: 'Lara glossary', isActive: false},
      isReadOnly: true,
    })

    expect(screen.getByTestId('laraglossary-active-lara-1')).toBeDisabled()
  })
})
