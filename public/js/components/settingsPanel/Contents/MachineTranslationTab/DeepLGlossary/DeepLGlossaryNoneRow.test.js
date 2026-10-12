import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {DeepLGlossaryNoneRow} from './DeepLGlossaryNoneRow'

const setup = ({row = {id: 'none', name: 'None', isActive: false}} = {}) => {
  const setRows = jest.fn()
  const utils = render(<DeepLGlossaryNoneRow row={row} setRows={setRows} />)
  return {setRows, row, ...utils}
}

describe('DeepLGlossaryNoneRow', () => {
  test('renders the glossary name', () => {
    setup({row: {id: 'none', name: 'None', isActive: false}})

    expect(screen.getByText('None')).toBeInTheDocument()
  })

  test('renders the radio unchecked when row is not active', () => {
    setup({row: {id: 'none', name: 'None', isActive: false}})

    expect(screen.getByTestId('deeplglossary-active-none')).not.toBeChecked()
  })

  test('renders the radio checked when row is active', () => {
    setup({row: {id: 'none', name: 'None', isActive: true}})

    expect(screen.getByTestId('deeplglossary-active-none')).toBeChecked()
  })

  test('syncs checked state when row.isActive prop changes', () => {
    const row = {id: 'none', name: 'None', isActive: false}
    const {rerender} = render(
      <DeepLGlossaryNoneRow row={row} setRows={jest.fn()} />,
    )

    expect(screen.getByTestId('deeplglossary-active-none')).not.toBeChecked()

    rerender(
      <DeepLGlossaryNoneRow
        row={{...row, isActive: true}}
        setRows={jest.fn()}
      />,
    )

    expect(screen.getByTestId('deeplglossary-active-none')).toBeChecked()
  })

  test('sets only this row active and deactivates the others on change', () => {
    const {setRows, row} = setup({
      row: {id: 'none', name: 'None', isActive: false},
    })

    fireEvent.click(screen.getByTestId('deeplglossary-active-none'))

    expect(setRows).toHaveBeenCalledWith(expect.any(Function))

    const updater = setRows.mock.calls[0][0]
    const result = updater([
      {id: 'gl-1', isActive: true},
      {id: row.id, isActive: false},
    ])

    expect(result).toEqual([
      {id: 'gl-1', isActive: false},
      {id: row.id, isActive: true},
    ])
  })
})
