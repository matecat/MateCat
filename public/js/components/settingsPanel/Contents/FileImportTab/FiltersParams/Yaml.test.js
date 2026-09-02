import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {Yaml} from './Yaml'
import {FiltersParamsContext} from './FiltersParamsContext'

const defaultYaml = {
  translate_keys: [],
  character_limit: [],
  context_keys: [],
  inner_content_type: null,
}

const setup = ({yamlOverrides = {}, currentProjectTemplateChanged = false} = {}) => {
  const modifyingCurrentTemplate = jest.fn()
  const currentTemplate = {id: 1, yaml: {...defaultYaml, ...yamlOverrides}}

  const utils = render(
    <FiltersParamsContext.Provider
      value={{
        currentTemplate,
        currentProjectTemplateChanged,
        modifyingCurrentTemplate,
      }}
    >
      <Yaml />
    </FiltersParamsContext.Provider>,
  )
  return {modifyingCurrentTemplate, currentTemplate, ...utils}
}

describe('Yaml', () => {
  test('renders all sections with default values', () => {
    setup()

    expect(screen.getByText('Translatable keys')).toBeInTheDocument()
    expect(screen.getByText('Context keys')).toBeInTheDocument()
    expect(screen.getByText('Character limit keys')).toBeInTheDocument()
    expect(screen.getByText('Translatable text content type')).toBeInTheDocument()
    expect(screen.getByText('Select inner content type')).toBeInTheDocument()
    expect(screen.getByTestId('radio-option-translate_keys')).toBeChecked()
  })

  test('defaults the segmented control to Non-translatable when do_not_translate_keys is defined', () => {
    setup({
      yamlOverrides: {translate_keys: undefined, do_not_translate_keys: ['a']},
    })

    expect(
      screen.getByTestId('radio-option-do_not_translate_keys'),
    ).toBeChecked()
  })

  test('switching segmented control updates the active option', async () => {
    const user = userEvent.setup()
    setup()

    await user.click(screen.getByTestId('radio-option-do_not_translate_keys'))

    await waitFor(() =>
      expect(
        screen.getByTestId('radio-option-do_not_translate_keys'),
      ).toBeChecked(),
    )
  })

  test('typing a context key reports the updated value', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    const inputs = screen.getAllByTestId('email-input')
    const contextKeysInput = inputs[1]

    await user.type(contextKeysInput, 'note,')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.yaml.context_keys).toEqual(['note'])
  })

  test('typing a character limit key reports the updated value', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    const inputs = screen.getAllByTestId('email-input')
    const characterLimitInput = inputs[2]

    await user.type(characterLimitInput, 'limitKey,')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.yaml.character_limit).toEqual(['limitKey'])
  })

  test('a key keeps its padding and the pill spells it out', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    const contextKeysInput = screen.getAllByTestId('email-input')[1]

    await user.type(contextKeysInput, ' my key ')
    await user.keyboard('{Enter}')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    // the space bar must not split a YAML key
    expect(updated.yaml.context_keys).toEqual([' my key '])
    expect(screen.getAllByText('·')).toHaveLength(2)
  })

  test('does not call modifyingCurrentTemplate when nothing changed', () => {
    const {modifyingCurrentTemplate} = setup()

    expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
  })
})
