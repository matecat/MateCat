import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {Dita} from './Dita'
import {FiltersParamsContext} from './FiltersParamsContext'

const setup = (
  {ditaOverrides = {}, currentProjectTemplateChanged = false} = {},
) => {
  const modifyingCurrentTemplate = jest.fn()
  const currentTemplate = {
    id: 1,
    dita: {do_not_translate_elements: [], ...ditaOverrides},
  }

  const utils = render(
    <FiltersParamsContext.Provider
      value={{
        currentTemplate,
        currentProjectTemplateChanged,
        modifyingCurrentTemplate,
      }}
    >
      <Dita />
    </FiltersParamsContext.Provider>,
  )
  return {modifyingCurrentTemplate, currentTemplate, ...utils}
}

describe('Dita', () => {
  test('renders the non-translatable elements field', () => {
    setup()

    expect(screen.getByText('Non-translatable elements')).toBeInTheDocument()
    expect(screen.getByTestId('email-input')).toBeInTheDocument()
  })

  test('defaults to an empty array when do_not_translate_elements is undefined', () => {
    setup({ditaOverrides: {do_not_translate_elements: undefined}})

    expect(screen.getByTestId('email-input')).toBeInTheDocument()
  })

  test('typing a non-translatable element reports the updated value', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    await user.type(screen.getByTestId('email-input'), 'note,')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.dita.do_not_translate_elements).toEqual(['note'])
  })

  test('does not call modifyingCurrentTemplate when nothing changed', () => {
    const {modifyingCurrentTemplate} = setup()

    expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
  })
})
