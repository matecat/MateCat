import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {Xliff20} from './Xliff20'
import {XliffSettingsContext} from './XliffSettingsContext'
import defaultXliffSettings from '../../defaultTemplates/xliffSettings.json'

class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

beforeAll(() => {
  window.ResizeObserver = ResizeObserver
})

const setup = ({rules = defaultXliffSettings.rules, templates} = {}) => {
  const currentTemplate = {
    id: defaultXliffSettings.id,
    isTemporary: false,
    rules,
  }
  const modifyingCurrentTemplate = jest.fn()

  const utils = render(
    <XliffSettingsContext.Provider
      value={{
        currentTemplate,
        modifyingCurrentTemplate,
        templates: templates ?? [currentTemplate],
      }}
    >
      <Xliff20 />
    </XliffSettingsContext.Provider>,
  )

  fireEvent.click(screen.getByText('XLIFF 2.0'))

  return {modifyingCurrentTemplate, currentTemplate, ...utils}
}

describe('Xliff20', () => {
  test('renders a row for each configured rule', () => {
    setup()

    expect(screen.getByText('XLIFF 2.0')).toBeInTheDocument()
    expect(screen.getByText('1.')).toBeInTheDocument()
    expect(screen.getByText('2.')).toBeInTheDocument()
    expect(screen.getByText('3.')).toBeInTheDocument()
    expect(screen.getByText('4.')).toBeInTheDocument()
  })

  const rulesWithAvailableState = {
    ...defaultXliffSettings.rules,
    xliff20: defaultXliffSettings.rules.xliff20.slice(0, 3),
  }

  test('shows the add rule button when a state is still available', () => {
    setup({rules: rulesWithAvailableState})

    expect(screen.getByText('Add rule')).toBeInTheDocument()
  })

  test('does not show the add rule button when every state is already used', () => {
    setup()

    expect(screen.queryByText('Add rule')).not.toBeInTheDocument()
  })

  test('adds a new row when the add rule button is clicked', () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup({
      rules: rulesWithAvailableState,
    })

    fireEvent.click(screen.getByText('Add rule'))

    expect(modifyingCurrentTemplate).toHaveBeenCalled()

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.rules.xliff20).toHaveLength(
      currentTemplate.rules.xliff20.length + 1,
    )
    expect(
      updated.rules.xliff20[updated.rules.xliff20.length - 1].analysis,
    ).toBe('new')
  })

  test('marks the section as modified when it differs from the original template', () => {
    const modifiedTemplate = {
      id: defaultXliffSettings.id,
      isTemporary: false,
      rules: {
        ...defaultXliffSettings.rules,
        xliff20: defaultXliffSettings.rules.xliff20.slice(0, 1),
      },
    }
    const originalTemplate = {
      id: defaultXliffSettings.id,
      isTemporary: false,
      rules: defaultXliffSettings.rules,
    }

    setup({
      rules: modifiedTemplate.rules,
      templates: [originalTemplate, modifiedTemplate],
    })

    expect(screen.getByText('●')).toBeInTheDocument()
  })

  test('removes a row when its delete button is clicked', () => {
    const {modifyingCurrentTemplate, currentTemplate, container} = setup()

    const deleteButtons = container.querySelectorAll(
      '.xliff-settings-table button',
    )
    fireEvent.click(deleteButtons[0])

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.rules.xliff20).toHaveLength(
      currentTemplate.rules.xliff20.length - 1,
    )
  })
})
