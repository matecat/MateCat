import React from 'react'
import {act, render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {XliffRulesRow} from './XliffRulesRow'
import xliffOptions from '../../defaultTemplates/xliffOptions.json'

class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

beforeAll(() => {
  window.ResizeObserver = ResizeObserver
})

const preTranslatedRow = {
  id: 0,
  states: ['translated', 'needs-review-l10n'],
  analysis: 'pre-translated',
  match_category: 'ice',
  editor: 'translated',
}

const newRow = {
  id: 1,
  states: ['new'],
  analysis: 'new',
}

const setup = (props = {}) => {
  const onChange = jest.fn()
  const onDelete = jest.fn()

  const utils = render(
    <XliffRulesRow
      value={preTranslatedRow}
      onChange={onChange}
      onDelete={onDelete}
      currentXliffData={[preTranslatedRow]}
      xliffOptions={xliffOptions.xliff12}
      {...props}
    />,
  )
  return {onChange, onDelete, ...utils}
}

describe('XliffRulesRow', () => {
  test('renders the row index', () => {
    setup()

    expect(screen.getByText('1.')).toBeInTheDocument()
  })

  test('renders the selected states', () => {
    setup()

    expect(
      screen.getByText("'translated', 'needs-review-l10n'"),
    ).toBeInTheDocument()
  })

  test('offers no-state as the value and shows it unquoted as "No state"', () => {
    const noStateRow = {id: 2, states: ['no-state'], analysis: 'new'}
    setup({value: noStateRow, currentXliffData: [noStateRow]})

    expect(xliffOptions.xliff12.states).toContain('no-state')
    expect(xliffOptions.xliff12.states).not.toContain('No state')
    expect(screen.getByText('No state')).toBeInTheDocument()
  })

  test('renders the match category name for a pre-translated analysis', () => {
    setup()

    expect(screen.getByText("Map to 'TM 101%'")).toBeInTheDocument()
  })

  test('renders N/A for a new row analysis and disables the editor select', () => {
    setup({
      value: newRow,
      currentXliffData: [newRow],
    })

    expect(
      screen.getByText('Ignore target (run TM analysis)'),
    ).toBeInTheDocument()
    expect(screen.getByText('N/A (determined by TM)')).toBeInTheDocument()
  })

  test('lists the editor states with their locked variants, draft without one', async () => {
    const user = userEvent.setup()
    const {container} = setup()

    await act(async () => user.click(container.querySelectorAll('.select')[2]))

    expect(screen.getByText("'draft'")).toBeInTheDocument()
    expect(screen.queryByText("'draft' (locked)")).not.toBeInTheDocument()
    ;['translated', 'approved', 'approved2'].forEach((status) =>
      expect(screen.getByText(`'${status}' (locked)`)).toBeInTheDocument(),
    )
  })

  test('a locked editor value writes lock: true', async () => {
    const user = userEvent.setup()
    const {onChange, container} = setup()

    await act(async () => user.click(container.querySelectorAll('.select')[2]))
    await act(async () => user.click(screen.getByText("'approved' (locked)")))

    expect(onChange).toHaveBeenLastCalledWith({
      ...preTranslatedRow,
      editor: 'approved',
      lock: true,
    })
  })

  test('shows a locked rule as its locked value', () => {
    const lockedRow = {...preTranslatedRow, editor: 'approved', lock: true}
    setup({value: lockedRow, currentXliffData: [lockedRow]})

    expect(screen.getByText("'approved' (locked)")).toBeInTheDocument()
  })

  test('an unlocked editor value removes lock', async () => {
    const user = userEvent.setup()
    const lockedRow = {...preTranslatedRow, editor: 'approved', lock: true}
    const {onChange, container} = setup({
      value: lockedRow,
      currentXliffData: [lockedRow],
    })

    await act(async () => user.click(container.querySelectorAll('.select')[2]))
    await act(async () => user.click(screen.getByText("'approved2'")))

    const lastValue = onChange.mock.calls.at(-1)[0]
    expect(lastValue).toEqual({...preTranslatedRow, editor: 'approved2'})
    expect(lastValue).not.toHaveProperty('lock')
  })

  test('draft writes no lock', async () => {
    const user = userEvent.setup()
    const lockedRow = {...preTranslatedRow, editor: 'approved', lock: true}
    const {onChange, container} = setup({
      value: lockedRow,
      currentXliffData: [lockedRow],
    })

    await act(async () => user.click(container.querySelectorAll('.select')[2]))
    await act(async () => user.click(screen.getByText("'draft'")))

    const lastValue = onChange.mock.calls.at(-1)[0]
    expect(lastValue).toEqual({...preTranslatedRow, editor: 'draft'})
    expect(lastValue).not.toHaveProperty('lock')
  })

  test('switching a locked rule to new drops lock', async () => {
    const user = userEvent.setup()
    const lockedRow = {...preTranslatedRow, editor: 'approved', lock: true}
    const {onChange, container} = setup({
      value: lockedRow,
      currentXliffData: [lockedRow],
    })

    await act(async () => user.click(container.querySelectorAll('.select')[1]))
    await act(async () =>
      user.click(screen.getByText('Ignore target (run TM analysis)')),
    )

    const lastValue = onChange.mock.calls.at(-1)[0]
    expect(lastValue).toEqual({
      id: 0,
      states: ['translated', 'needs-review-l10n'],
      analysis: 'new',
    })
    expect(container.querySelectorAll('.select')[2]).toHaveClass(
      'select--is-disabled',
    )
  })

  test('calls onDelete with the row id when the delete button is clicked', () => {
    const {onDelete, container} = setup()

    const deleteButton = container.querySelector('button')
    deleteButton.click()

    expect(onDelete).toHaveBeenCalledWith(0)
  })
})
