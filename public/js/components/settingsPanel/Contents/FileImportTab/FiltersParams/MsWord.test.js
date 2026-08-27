import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {MsWord} from './MsWord'
import {FiltersParamsContext} from './FiltersParamsContext'

const defaultMsWord = {
  extract_doc_properties: false,
  extract_comments: false,
  extract_headers_footers: true,
  extract_hidden_text: false,
  accept_revisions: false,
  exclude_styles: [],
  exclude_highlight_colors: [],
}

const setup = (
  {msWordOverrides = {}, currentProjectTemplateChanged = false} = {},
) => {
  const modifyingCurrentTemplate = jest.fn()
  const currentTemplate = {id: 1, msWord: {...defaultMsWord, ...msWordOverrides}}

  const utils = render(
    <FiltersParamsContext.Provider
      value={{
        currentTemplate,
        currentProjectTemplateChanged,
        modifyingCurrentTemplate,
      }}
    >
      <MsWord />
    </FiltersParamsContext.Provider>,
  )
  return {modifyingCurrentTemplate, currentTemplate, ...utils}
}

describe('MsWord', () => {
  test('renders all sections with default values', () => {
    setup()

    expect(
      screen.getByText('Translate headers and footers'),
    ).toBeInTheDocument()
    expect(screen.getByText('Translate hidden text')).toBeInTheDocument()
    expect(screen.getByText('Translate comments')).toBeInTheDocument()
    expect(
      screen.getByText('Translate documents properties'),
    ).toBeInTheDocument()
    expect(
      screen.getByText('Automatically accept revisions'),
    ).toBeInTheDocument()
    expect(screen.getByText('Exclude styles')).toBeInTheDocument()
    expect(screen.getByText('Exclude highlight colors')).toBeInTheDocument()

    expect(
      document.querySelector('input[name="extract_headers_footers"]'),
    ).toBeChecked()
    expect(
      document.querySelector('input[name="extract_hidden_text"]'),
    ).not.toBeChecked()
    expect(
      document.querySelector('input[name="extract_comments"]'),
    ).not.toBeChecked()
    expect(
      document.querySelector('input[name="extract_doc_properties"]'),
    ).not.toBeChecked()
    expect(
      document.querySelector('input[name="accept_revisions"]'),
    ).not.toBeChecked()
  })

  test('toggling extract_hidden_text switch reports the updated value', async () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    fireEvent.click(document.querySelector('input[name="extract_hidden_text"]'))

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.msWord.extract_hidden_text).toBe(true)
  })

  test('toggling accept_revisions switch reports the updated value', async () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    fireEvent.click(document.querySelector('input[name="accept_revisions"]'))

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.msWord.accept_revisions).toBe(true)
  })

  test('typing an excluded style reports the updated value', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    const excludeStylesInput = screen.getAllByTestId('email-input')[0]

    await user.type(excludeStylesInput, 'testStyle,')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.msWord.exclude_styles).toEqual(['testStyle'])
  })

  test('typing an excluded highlight color reports the updated value', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    const excludeColorsInput = screen.getAllByTestId('email-input')[1]

    await user.type(excludeColorsInput, 'yellow,')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.msWord.exclude_highlight_colors).toEqual(['yellow'])
  })

  test('does not call modifyingCurrentTemplate when nothing changed', () => {
    const {modifyingCurrentTemplate} = setup()

    expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
  })
})
