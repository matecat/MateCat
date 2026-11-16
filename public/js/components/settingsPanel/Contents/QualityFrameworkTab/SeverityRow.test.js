import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {SeveritiyRow} from './SeverityRow'
import {QualityFrameworkTabContext} from './QualityFrameworkTabContext'

const severity = {id: 's1', id_category: 'c1', penalty: 5}

const renderRow = ({
  modifyingCurrentTemplate = jest.fn(),
  templates = [],
  currentTemplate = {id: 1, categories: []},
  severityOverride = severity,
} = {}) =>
  render(
    <QualityFrameworkTabContext.Provider
      value={{modifyingCurrentTemplate, templates, currentTemplate}}
    >
      <SeveritiyRow severity={severityOverride} />
    </QualityFrameworkTabContext.Provider>,
  )

describe('SeveritiyRow', () => {
  test('renders the penalty input with the initial value', () => {
    renderRow()

    expect(screen.getByRole('textbox')).toHaveValue('5')
  })

  test('renders the remove-severity icon at the current size', () => {
    renderRow()

    // eslint-disable-next-line testing-library/no-node-access
    const icon = screen.getByTestId('qf-severity-cell-c1-s1').querySelector('svg')
    expect(icon).toHaveAttribute('width', '14')
    expect(icon).toHaveAttribute('height', '14')
  })

  test('accepts digits, dots and plus signs', () => {
    renderRow()
    const input = screen.getByRole('textbox')

    fireEvent.change(input, {target: {value: '12.5'}})

    expect(input).toHaveValue('12.5')
  })

  test('rejects characters other than digits, dots and plus signs', () => {
    renderRow()
    const input = screen.getByRole('textbox')

    fireEvent.change(input, {target: {value: '12a'}})

    expect(input).toHaveValue('5')
  })

  test('selects the input contents on focus', () => {
    const selectSpy = jest.spyOn(HTMLInputElement.prototype, 'select')
    renderRow()

    fireEvent.focus(screen.getByRole('textbox'))

    expect(selectSpy).toHaveBeenCalled()
    selectSpy.mockRestore()
  })

  test('commits the parsed penalty for the matching severity on blur', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderRow({modifyingCurrentTemplate})
    const input = screen.getByRole('textbox')

    fireEvent.change(input, {target: {value: '7.5'}})
    fireEvent.blur(input)

    expect(modifyingCurrentTemplate).toHaveBeenCalledTimes(1)
    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    const result = updater({
      categories: [
        {
          id: 'c1',
          severities: [{id: 's1', penalty: 5}],
        },
        {
          id: 'c2',
          severities: [{id: 's2', penalty: 1}],
        },
      ],
    })

    expect(
      result.categories.find((c) => c.id === 'c1').severities[0].penalty,
    ).toBe(7.5)
    // Other categories/severities are left untouched.
    expect(
      result.categories.find((c) => c.id === 'c2').severities[0].penalty,
    ).toBe(1)
  })

  test('reverts the displayed value and keeps the stored penalty when blurred empty', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderRow({modifyingCurrentTemplate})
    const input = screen.getByRole('textbox')

    fireEvent.change(input, {target: {value: ''}})
    fireEvent.blur(input)

    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    const result = updater({
      categories: [{id: 'c1', severities: [{id: 's1', penalty: 5}]}],
    })
    expect(
      result.categories.find((c) => c.id === 'c1').severities[0].penalty,
    ).toBe(5)

    expect(input).toHaveValue('5')
  })

  test('syncs the displayed penalty when the severity prop changes', () => {
    const {rerender} = render(
      <QualityFrameworkTabContext.Provider
        value={{
          modifyingCurrentTemplate: jest.fn(),
          templates: [],
          currentTemplate: {id: 1, categories: []},
        }}
      >
        <SeveritiyRow severity={severity} />
      </QualityFrameworkTabContext.Provider>,
    )

    rerender(
      <QualityFrameworkTabContext.Provider
        value={{
          modifyingCurrentTemplate: jest.fn(),
          templates: [],
          currentTemplate: {id: 1, categories: []},
        }}
      >
        <SeveritiyRow severity={{...severity, penalty: 9}} />
      </QualityFrameworkTabContext.Provider>,
    )

    expect(screen.getByRole('textbox')).toHaveValue('9')
  })

  test('removes only the matching severity from its category on remove click', () => {
    const modifyingCurrentTemplate = jest.fn()
    renderRow({modifyingCurrentTemplate})

    fireEvent.click(screen.getByRole('button'))

    const updater = modifyingCurrentTemplate.mock.calls[0][0]
    const result = updater({
      categories: [
        {
          id: 'c1',
          severities: [
            {id: 's1', penalty: 5},
            {id: 's1-sibling', penalty: 2},
          ],
        },
        {
          id: 'c2',
          severities: [{id: 's1', penalty: 3}],
        },
      ],
    })

    expect(
      result.categories.find((c) => c.id === 'c1').severities,
    ).toEqual([{id: 's1-sibling', penalty: 2}])
    // A severity with the same id in a different category is untouched.
    expect(
      result.categories.find((c) => c.id === 'c2').severities,
    ).toEqual([{id: 's1', penalty: 3}])
  })

  test('is not marked unsaved when no template is in a temporary (draft) state', () => {
    renderRow({
      templates: [{id: 1, isTemporary: false, categories: []}],
      currentTemplate: {id: 1, categories: []},
    })

    expect(screen.getByTestId('qf-severity-cell-c1-s1')).not.toHaveClass(
      'cell-not-saved',
    )
  })

  test('is marked unsaved when the severity does not exist in the saved template', () => {
    const originalTemplate = {
      id: 1,
      isTemporary: false,
      categories: [{id: 'c1', severities: []}],
    }
    const draftMarker = {id: 2, isTemporary: true, categories: []}

    renderRow({
      templates: [originalTemplate, draftMarker],
      currentTemplate: {
        id: 1,
        categories: [{id: 'c1', severities: [{id: 's1', penalty: 5}]}],
      },
    })

    expect(screen.getByTestId('qf-severity-cell-c1-s1')).toHaveClass(
      'cell-not-saved',
    )
  })

  test('is marked unsaved when the penalty differs from the saved template', () => {
    const originalTemplate = {
      id: 1,
      isTemporary: false,
      categories: [{id: 'c1', severities: [{id: 's1', penalty: 5}]}],
    }
    const draftMarker = {id: 2, isTemporary: true, categories: []}

    renderRow({
      templates: [originalTemplate, draftMarker],
      currentTemplate: {
        id: 1,
        categories: [{id: 'c1', severities: [{id: 's1', penalty: 7}]}],
      },
    })

    expect(screen.getByTestId('qf-severity-cell-c1-s1')).toHaveClass(
      'cell-not-saved',
    )
  })

  test('is not marked unsaved when the penalty matches the saved template', () => {
    const originalTemplate = {
      id: 1,
      isTemporary: false,
      categories: [{id: 'c1', severities: [{id: 's1', penalty: 5}]}],
    }
    const draftMarker = {id: 2, isTemporary: true, categories: []}

    renderRow({
      templates: [originalTemplate, draftMarker],
      currentTemplate: {
        id: 1,
        categories: [{id: 'c1', severities: [{id: 's1', penalty: 5}]}],
      },
    })

    expect(screen.getByTestId('qf-severity-cell-c1-s1')).not.toHaveClass(
      'cell-not-saved',
    )
  })
})
