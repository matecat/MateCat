import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {SettingsPanelTable} from './SettingsPanelTable'
import {SettingsPanelTableContext} from './SettingsPanelTableContext'
import {SPECIAL_ROWS_ID} from '../Contents/TranslationMemoryGlossaryTab/TranslationMemoryGlossaryTabUtils'

jest.mock('../Contents/TranslationMemoryGlossaryTab/TmPrioritization', () => ({
  TmPrioritization: () => <div data-testid="tm-prioritization" />,
}))

jest.mock('./SettingsPanelRow', () => {
  const React = require('react')
  const {SettingsPanelTableContext} = require('./SettingsPanelTableContext')
  return {
    SettingsPanelRow: React.forwardRef(
      ({index, row, isDragOver, wasDragged}, ref) => {
        const {onDragStart, onDragOver, onDragEnd} = React.useContext(
          SettingsPanelTableContext,
        )
        return (
          <div
            ref={ref}
            data-testid={`row-${row.id}`}
            data-drag-over={String(!!isDragOver)}
            data-was-dragged={String(!!wasDragged)}
          >
            <button
              data-testid={`drag-start-${row.id}`}
              onClick={() => onDragStart(index)}
            >
              start
            </button>
            <button
              data-testid={`drag-over-${row.id}`}
              onClick={() =>
                onDragOver({y: 0, halfPoint: 'top', row})
              }
            >
              over
            </button>
            <button
              data-testid={`drag-end-${row.id}`}
              onClick={() => onDragEnd({row})}
            >
              end
            </button>
          </div>
        )
      },
    ),
  }
})

const buildRow = (id, overrides = {}) => ({
  id,
  node: <span>{id}</span>,
  ...overrides,
})

describe('SettingsPanelTable', () => {
  test('renders column headings', () => {
    render(
      <SettingsPanelTable
        columns={[{name: 'Col A'}, {name: 'Col B'}]}
        rows={[]}
      />,
    )
    expect(screen.getByText('Col A')).toBeInTheDocument()
    expect(screen.getByText('Col B')).toBeInTheDocument()
  })

  test('renders the empty state when rows is an empty array', () => {
    const {container} = render(
      <SettingsPanelTable columns={[{name: 'Col A'}]} rows={[]} />,
    )
    expect(
      container.querySelector('.settings-panel-table-row-empty'),
    ).toBeInTheDocument()
  })

  test('renders a row per item', () => {
    const rows = [buildRow('a'), buildRow('b')]
    render(<SettingsPanelTable columns={[{name: 'Col A'}]} rows={rows} />)
    expect(screen.getByTestId('row-a')).toBeInTheDocument()
    expect(screen.getByTestId('row-b')).toBeInTheDocument()
  })

  test('applies a custom className to the table wrapper', () => {
    const {container} = render(
      <SettingsPanelTable
        columns={[{name: 'Col A'}]}
        rows={[]}
        className="my-custom-class"
      />,
    )
    expect(
      container.querySelector('.settings-panel-table.my-custom-class'),
    ).toBeInTheDocument()
  })

  test('renders TmPrioritization after the default TM row when there are more than two rows', () => {
    const rows = [
      buildRow(SPECIAL_ROWS_ID.defaultTranslationMemory),
      buildRow('b'),
      buildRow('c'),
    ]
    render(<SettingsPanelTable columns={[{name: 'Col A'}]} rows={rows} />)
    expect(screen.getByTestId('tm-prioritization')).toBeInTheDocument()
  })

  test('does not render TmPrioritization when there are two rows or fewer', () => {
    const rows = [
      buildRow(SPECIAL_ROWS_ID.defaultTranslationMemory),
      buildRow('b'),
    ]
    render(<SettingsPanelTable columns={[{name: 'Col A'}]} rows={rows} />)
    expect(screen.queryByTestId('tm-prioritization')).not.toBeInTheDocument()
  })

  test('drag start then drag over marks the target row as dragged over', () => {
    const rows = [buildRow('a'), buildRow('b'), buildRow('c')]
    render(<SettingsPanelTable columns={[{name: 'Col A'}]} rows={rows} />)

    fireEvent.click(screen.getByTestId('drag-start-c'))
    fireEvent.click(screen.getByTestId('drag-over-a'))

    expect(screen.getByTestId('row-a').dataset.dragOver).toBe('true')
    expect(screen.getByTestId('row-b').dataset.dragOver).toBe('false')
  })

  test('completing a drag reorders using the start and target indices', () => {
    const onChangeRowsOrder = jest.fn()
    const rows = [buildRow('a'), buildRow('b'), buildRow('c')]
    render(
      <SettingsPanelTable
        columns={[{name: 'Col A'}]}
        rows={rows}
        onChangeRowsOrder={onChangeRowsOrder}
      />,
    )

    fireEvent.click(screen.getByTestId('drag-start-c'))
    fireEvent.click(screen.getByTestId('drag-over-a'))
    fireEvent.click(screen.getByTestId('drag-end-c'))

    expect(onChangeRowsOrder).toHaveBeenCalledWith({index: 2, indexToMove: 0})
    expect(screen.getByTestId('row-a').dataset.dragOver).toBe('false')
    expect(screen.getByTestId('row-c').dataset.wasDragged).toBe('true')
  })

  test('dragging over a locked row clears the drag over target without reordering', () => {
    const onChangeRowsOrder = jest.fn()
    const rows = [buildRow('a', {isLocked: true}), buildRow('b'), buildRow('c')]
    render(
      <SettingsPanelTable
        columns={[{name: 'Col A'}]}
        rows={rows}
        onChangeRowsOrder={onChangeRowsOrder}
      />,
    )

    fireEvent.click(screen.getByTestId('drag-start-c'))
    fireEvent.click(screen.getByTestId('drag-over-a'))
    fireEvent.click(screen.getByTestId('drag-end-c'))

    expect(onChangeRowsOrder).not.toHaveBeenCalled()
  })
})
