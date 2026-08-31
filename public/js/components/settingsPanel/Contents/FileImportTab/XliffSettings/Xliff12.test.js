import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {Xliff12} from './Xliff12'
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
      <Xliff12 />
    </XliffSettingsContext.Provider>,
  )
  fireEvent.click(screen.getByText('XLIFF 1.2'))

  return {modifyingCurrentTemplate, currentTemplate, ...utils}
}

describe('Xliff12', () => {
  test('renders a row for each configured rule', () => {
    setup()

    expect(screen.getByText('XLIFF 1.2')).toBeInTheDocument()
    expect(screen.getByText('1.')).toBeInTheDocument()
    expect(screen.getByText('2.')).toBeInTheDocument()
    expect(screen.getByText('3.')).toBeInTheDocument()
    expect(screen.getByText('4.')).toBeInTheDocument()
  })

  test('shows the add rule button when a state is still available', () => {
    setup()

    expect(screen.getByText('Add rule')).toBeInTheDocument()
  })

  test('adds a new row when the add rule button is clicked', () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    fireEvent.click(screen.getByText('Add rule'))

    expect(modifyingCurrentTemplate).toHaveBeenCalled()

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.rules.xliff12).toHaveLength(
      currentTemplate.rules.xliff12.length + 1,
    )
    expect(
      updated.rules.xliff12[updated.rules.xliff12.length - 1].analysis,
    ).toBe('new')
  })

  test('marks the section as modified when it differs from the original template', () => {
    const modifiedTemplate = {
      id: defaultXliffSettings.id,
      isTemporary: false,
      rules: {
        ...defaultXliffSettings.rules,
        xliff12: defaultXliffSettings.rules.xliff12.slice(0, 1),
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
    expect(updated.rules.xliff12).toHaveLength(
      currentTemplate.rules.xliff12.length - 1,
    )
  })
})
