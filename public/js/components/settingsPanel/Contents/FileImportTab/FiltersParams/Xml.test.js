import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {Xml} from './Xml'
import {FiltersParamsContext} from './FiltersParamsContext'

const defaultXml = {
  preserve_whitespace: false,
  translate_elements: [],
  translate_attributes: [],
}

const setup = ({xmlOverrides = {}, currentProjectTemplateChanged = false} = {}) => {
  const modifyingCurrentTemplate = jest.fn()
  const currentTemplate = {id: 1, xml: {...defaultXml, ...xmlOverrides}}

  const utils = render(
    <FiltersParamsContext.Provider
      value={{
        currentTemplate,
        currentProjectTemplateChanged,
        modifyingCurrentTemplate,
      }}
    >
      <Xml />
    </FiltersParamsContext.Provider>,
  )
  return {modifyingCurrentTemplate, currentTemplate, ...utils}
}

describe('Xml', () => {
  test('renders all sections with default values', () => {
    setup()

    expect(screen.getByText('Preserve whitespaces')).toBeInTheDocument()
    expect(screen.getByText('Translatable elements')).toBeInTheDocument()
    expect(screen.getByText('Translatable attributes')).toBeInTheDocument()
    expect(
      document.querySelector('input[name="preserve_whitespace"]'),
    ).not.toBeChecked()
    expect(screen.getByTestId('radio-option-translate_elements')).toBeChecked()
  })

  test('defaults segmented control to Non-translatable when do_not_translate_elements is defined', () => {
    setup({
      xmlOverrides: {
        translate_elements: undefined,
        do_not_translate_elements: ['div'],
      },
    })

    expect(
      screen.getByTestId('radio-option-do_not_translate_elements'),
    ).toBeChecked()
  })

  test('toggling preserve_whitespace switch reports the updated value', async () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    fireEvent.click(
      document.querySelector('input[name="preserve_whitespace"]'),
    )

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.xml.preserve_whitespace).toBe(true)
  })

  test('switching segmented control updates the active option', async () => {
    const user = userEvent.setup()
    setup()

    await user.click(
      screen.getByTestId('radio-option-do_not_translate_elements'),
    )

    await waitFor(() =>
      expect(
        screen.getByTestId('radio-option-do_not_translate_elements'),
      ).toBeChecked(),
    )
  })

  test('typing a translatable attribute reports the updated value', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    const attributesInput = screen.getAllByTestId('email-input')[1]

    await user.type(attributesInput, 'p@class,')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.xml.translate_attributes).toEqual(['p@class'])
  })

  test('the space bar and the comma both close a pill, without the padding', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    const elementsInput = screen.getAllByTestId('email-input')[0]

    await user.type(elementsInput, ' div span,p ')
    await user.keyboard('{Enter}')

    await waitFor(() => expect(modifyingCurrentTemplate).toHaveBeenCalled())

    const updater =
      modifyingCurrentTemplate.mock.calls[
        modifyingCurrentTemplate.mock.calls.length - 1
      ][0]
    const updated = updater(currentTemplate)
    expect(updated.xml.translate_elements).toEqual(['div', 'span', 'p'])
  })

  test('does not call modifyingCurrentTemplate when nothing changed', () => {
    const {modifyingCurrentTemplate} = setup()

    expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
  })
})
