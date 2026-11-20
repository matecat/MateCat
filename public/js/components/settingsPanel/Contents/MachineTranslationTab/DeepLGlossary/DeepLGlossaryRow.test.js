import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {DeepLGlossaryRow} from './DeepLGlossaryRow'

const setup = ({
  row = {id: 'gl-1', name: 'Glossary 1', isActive: false},
  isReadOnly = false,
  deleteGlossaryConfirm = jest.fn(),
} = {}) => {
  const setRows = jest.fn()
  const utils = render(
    <DeepLGlossaryRow
      engineId={1}
      row={row}
      setRows={setRows}
      isReadOnly={isReadOnly}
      deleteGlossaryConfirm={deleteGlossaryConfirm}
    />,
  )
  return {setRows, row, deleteGlossaryConfirm, ...utils}
}

describe('DeepLGlossaryRow', () => {
  test('renders the glossary name', () => {
    setup({row: {id: 'gl-1', name: 'Glossary 1', isActive: false}})

    expect(screen.getByText('Glossary 1')).toBeInTheDocument()
  })

  test('renders the radio unchecked when row is not active', () => {
    setup({row: {id: 'gl-1', name: 'Glossary 1', isActive: false}})

    expect(screen.getByTestId('deeplglossary-active-gl-1')).not.toBeChecked()
  })

  test('renders the radio checked when row is active', () => {
    setup({row: {id: 'gl-1', name: 'Glossary 1', isActive: true}})

    expect(screen.getByTestId('deeplglossary-active-gl-1')).toBeChecked()
  })

  test('syncs checked state when row.isActive prop changes', () => {
    const row = {id: 'gl-1', name: 'Glossary 1', isActive: false}
    const {rerender} = render(
      <DeepLGlossaryRow
        engineId={1}
        row={row}
        setRows={jest.fn()}
        isReadOnly={false}
        deleteGlossaryConfirm={jest.fn()}
      />,
    )

    expect(screen.getByTestId('deeplglossary-active-gl-1')).not.toBeChecked()

    rerender(
      <DeepLGlossaryRow
        engineId={1}
        row={{...row, isActive: true}}
        setRows={jest.fn()}
        isReadOnly={false}
        deleteGlossaryConfirm={jest.fn()}
      />,
    )

    expect(screen.getByTestId('deeplglossary-active-gl-1')).toBeChecked()
  })

  test('sets only this row active and deactivates the others on change', () => {
    const {setRows, row} = setup({
      row: {id: 'gl-1', name: 'Glossary 1', isActive: false},
    })

    fireEvent.click(screen.getByTestId('deeplglossary-active-gl-1'))

    expect(setRows).toHaveBeenCalledWith(expect.any(Function))

    const updater = setRows.mock.calls[0][0]
    const result = updater([
      {id: 'gl-2', isActive: true},
      {id: row.id, isActive: false},
    ])

    expect(result).toEqual([
      {id: 'gl-2', isActive: false},
      {id: row.id, isActive: true},
    ])
  })

  test('renders the delete button and import placeholder when not read-only', () => {
    setup({row: {id: 'gl-1', name: 'Glossary 1', isActive: false}})

    expect(
      screen.getByTestId('delete-deeplglossary-gl-1'),
    ).toBeInTheDocument()
  })

  test('calls deleteGlossaryConfirm with the row when the delete button is clicked', () => {
    const deleteGlossaryConfirm = jest.fn()
    const row = {id: 'gl-1', name: 'Glossary 1', isActive: false}
    setup({row, deleteGlossaryConfirm})

    fireEvent.click(screen.getByTestId('delete-deeplglossary-gl-1'))

    expect(deleteGlossaryConfirm).toHaveBeenCalledWith(row)
  })

  test('hides the delete button when read-only', () => {
    setup({
      row: {id: 'gl-1', name: 'Glossary 1', isActive: false},
      isReadOnly: true,
    })

    expect(
      screen.queryByTestId('delete-deeplglossary-gl-1'),
    ).not.toBeInTheDocument()
  })
})
