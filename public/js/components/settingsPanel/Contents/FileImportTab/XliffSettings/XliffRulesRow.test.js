import React from 'react'
import {render, screen} from '@testing-library/react'
import {XliffRulesRow} from './XliffRulesRow'
import xliffOptions from '../../defaultTemplates/xliffOptions.json'

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
      screen.getByText('translated, needs-review-l10n'),
    ).toBeInTheDocument()
  })

  test('renders the match category name for a pre-translated analysis', () => {
    setup()

    expect(screen.getByText('TM 101%')).toBeInTheDocument()
  })

  test('renders N.A. for a new row analysis and disables the editor select', () => {
    setup({
      value: newRow,
      currentXliffData: [newRow],
    })

    expect(screen.getAllByText('N.A.').length).toBeGreaterThan(0)
  })

  test('calls onDelete with the row id when the delete button is clicked', () => {
    const {onDelete, container} = setup()

    const deleteButton = container.querySelector('button')
    deleteButton.click()

    expect(onDelete).toHaveBeenCalledWith(0)
  })
})
