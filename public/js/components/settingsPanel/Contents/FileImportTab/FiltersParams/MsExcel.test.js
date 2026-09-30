import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {MsExcel} from './MsExcel'
import {FiltersParamsContext} from './FiltersParamsContext'

const defaultMsExcel = {
  extract_doc_properties: false,
  extract_hidden_cells: false,
  extract_diagrams: false,
  extract_drawings: false,
  extract_sheet_names: false,
  exclude_columns: [],
}

const setup = (
  {msExcelOverrides = {}, currentProjectTemplateChanged = false} = {},
) => {
  const modifyingCurrentTemplate = jest.fn()
  const currentTemplate = {
    id: 1,
    msExcel: {...defaultMsExcel, ...msExcelOverrides},
  }

  const utils = render(
    <FiltersParamsContext.Provider
      value={{
        currentTemplate,
        currentProjectTemplateChanged,
        modifyingCurrentTemplate,
      }}
    >
      <MsExcel />
    </FiltersParamsContext.Provider>,
  )
  return {modifyingCurrentTemplate, currentTemplate, ...utils}
}

describe('MsExcel', () => {
  test('renders all sections with default values', () => {
    setup()

    expect(screen.getByText('Translate hidden cells')).toBeInTheDocument()
    expect(screen.getByText('Translate chart texts')).toBeInTheDocument()
    expect(screen.getByText('Translate text boxes')).toBeInTheDocument()
    expect(screen.getByText('Translate sheet names')).toBeInTheDocument()
    expect(
      screen.getByText('Translate document properties'),
    ).toBeInTheDocument()
    expect(screen.getByText('Exclude columns')).toBeInTheDocument()

    expect(
      document.querySelector('input[name="extract_hidden_cells"]'),
    ).not.toBeChecked()
  })

  test('toggling extract_sheet_names switch reports the updated value', async () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    fireEvent.click(
      document.querySelector('input[name="extract_sheet_names"]'),
    )

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.msExcel.extract_sheet_names).toBe(true)
  })

  test('toggling extract_diagrams switch reports the updated value', async () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    fireEvent.click(document.querySelector('input[name="extract_diagrams"]'))

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.msExcel.extract_diagrams).toBe(true)
  })

  test('typing an excluded column reports the updated value', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    const excludeColumnsInput = screen.getByTestId('email-input')

    await user.type(excludeColumnsInput, '1C,')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.msExcel.exclude_columns).toEqual(['1C'])
  })

  test('does not call modifyingCurrentTemplate when nothing changed', () => {
    const {modifyingCurrentTemplate} = setup()

    expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
  })
})
