import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {Json} from './Json'
import {FiltersParamsContext} from './FiltersParamsContext'

const defaultJson = {
  extract_arrays: true,
  escape_forward_slashes: false,
  translate_keys: [],
  context_keys: [],
  character_limit: [],
  inner_content_type: null,
}

const setup = ({jsonOverrides = {}, currentProjectTemplateChanged = false} = {}) => {
  const modifyingCurrentTemplate = jest.fn()
  const currentTemplate = {id: 1, json: {...defaultJson, ...jsonOverrides}}

  const utils = render(
    <FiltersParamsContext.Provider
      value={{
        currentTemplate,
        currentProjectTemplateChanged,
        modifyingCurrentTemplate,
      }}
    >
      <Json />
    </FiltersParamsContext.Provider>,
  )
  return {modifyingCurrentTemplate, currentTemplate, ...utils}
}

describe('Json', () => {
  test('renders all sections with default values', () => {
    setup()

    expect(screen.getByText('Translate arrays')).toBeInTheDocument()
    expect(screen.getByText('Escape forward slashes')).toBeInTheDocument()
    expect(screen.getByText('Translatable keys')).toBeInTheDocument()
    expect(screen.getByText('Context keys')).toBeInTheDocument()
    expect(screen.getByText('Character limit keys')).toBeInTheDocument()
    expect(screen.getByText('Translatable text content type')).toBeInTheDocument()
    expect(screen.getByText('Select inner content type')).toBeInTheDocument()

    expect(
      document.querySelector('input[name="extract_arrays"]'),
    ).toBeChecked()
    expect(
      document.querySelector('input[name="escape_forward_slashes"]'),
    ).not.toBeChecked()
  })

  test('defaults the segmented control to Translatable when translate_keys is defined', () => {
    setup()

    expect(screen.getByTestId('radio-option-translate_keys')).toBeChecked()
    expect(
      screen.getByTestId('radio-option-do_not_translate_keys'),
    ).not.toBeChecked()
  })

  test('defaults the segmented control to Non-translatable when do_not_translate_keys is defined', () => {
    setup({
      jsonOverrides: {
        translate_keys: undefined,
        do_not_translate_keys: ['foo'],
      },
    })

    expect(
      screen.getByTestId('radio-option-do_not_translate_keys'),
    ).toBeChecked()
  })

  test('toggling extract_arrays switch reports the updated value', async () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    fireEvent.click(document.querySelector('input[name="extract_arrays"]'))

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.json.extract_arrays).toBe(false)
  })

  test('switching to Non-translatable updates the active segmented control', async () => {
    const user = userEvent.setup()
    setup()

    await user.click(screen.getByTestId('radio-option-do_not_translate_keys'))

    await waitFor(() =>
      expect(
        screen.getByTestId('radio-option-do_not_translate_keys'),
      ).toBeChecked(),
    )
  })

  test('typing a context key adds it via the WordsBadge', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    const inputs = screen.getAllByTestId('email-input')
    // order: active segmented control field, context keys, character limit
    const contextKeysInput = inputs[1]

    await user.type(contextKeysInput, 'note,')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.json.context_keys).toEqual(['note'])
  })

  test('does not call modifyingCurrentTemplate when nothing changed', () => {
    const {modifyingCurrentTemplate} = setup()

    expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
  })
})
