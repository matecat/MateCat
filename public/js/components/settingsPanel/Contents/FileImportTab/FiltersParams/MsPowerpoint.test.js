import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {MsPowerpoint} from './MsPowerpoint'
import {FiltersParamsContext} from './FiltersParamsContext'

const defaultMsPowerpoint = {
  extract_doc_properties: false,
  extract_notes: true,
  translate_slides: [],
}

const setup = (
  {msPowerpointOverrides = {}, currentProjectTemplateChanged = false} = {},
) => {
  const modifyingCurrentTemplate = jest.fn()
  const currentTemplate = {
    id: 1,
    msPowerpoint: {...defaultMsPowerpoint, ...msPowerpointOverrides},
  }

  const utils = render(
    <FiltersParamsContext.Provider
      value={{
        currentTemplate,
        currentProjectTemplateChanged,
        modifyingCurrentTemplate,
      }}
    >
      <MsPowerpoint />
    </FiltersParamsContext.Provider>,
  )
  return {modifyingCurrentTemplate, currentTemplate, ...utils}
}

describe('MsPowerpoint', () => {
  test('renders all sections with default values', () => {
    setup()

    expect(screen.getByText('Translate hidden slides')).toBeInTheDocument()
    expect(screen.getByText('Translate speaker notes')).toBeInTheDocument()
    expect(
      screen.getByText('Translate document properties'),
    ).toBeInTheDocument()
    expect(screen.getByText('Translatable slides')).toBeInTheDocument()

    expect(
      document.querySelector('input[name="extract_hidden_slides"]'),
    ).not.toBeChecked()
    expect(document.querySelector('input[name="extract_notes"]')).toBeChecked()
    expect(screen.getByTestId('email-input')).not.toBeDisabled()
  })

  test('converts a contiguous range into a single chip on render', () => {
    setup({msPowerpointOverrides: {translate_slides: [1, 2, 3, 5]}})

    expect(screen.getByText('1-3')).toBeInTheDocument()
    expect(screen.getByText('5')).toBeInTheDocument()
  })

  test('typing a single slide number reports the expanded numeric value', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    await user.type(screen.getByTestId('email-input'), '7,')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.msPowerpoint.translate_slides).toEqual([7])
  })

  test('typing a slide range reports the expanded numeric values', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    await user.type(screen.getByTestId('email-input'), '5-7,')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.msPowerpoint.translate_slides).toEqual([5, 6, 7])
  })

  test('toggling extract_hidden_slides disables translatable slides and drops the value', async () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup({
      msPowerpointOverrides: {translate_slides: [1, 2]},
    })

    fireEvent.click(
      document.querySelector('input[name="extract_hidden_slides"]'),
    )

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.msPowerpoint.extract_hidden_slides).toBe(true)
    expect(updated.msPowerpoint.translate_slides).toBeUndefined()

    await waitFor(() => expect(screen.getByTestId('email-input')).toBeDisabled())
  })

  test('toggling extract_notes switch reports the updated value', async () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    fireEvent.click(document.querySelector('input[name="extract_notes"]'))

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.msPowerpoint.extract_notes).toBe(false)
  })

  test('the space bar closes a slide pill', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    await user.type(screen.getByTestId('email-input'), '1 3 5')
    await user.keyboard('{Enter}')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    // the panel converts the pills to slide numbers before it saves
    expect(updated.msPowerpoint.translate_slides).toEqual([1, 3, 5])
  })

  test('does not call modifyingCurrentTemplate when nothing changed', () => {
    const {modifyingCurrentTemplate} = setup()

    expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
  })
})
