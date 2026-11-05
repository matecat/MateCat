import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {Markdown} from './Markdown'
import {FiltersParamsContext} from './FiltersParamsContext'

const defaultMarkdown = {
  extract_frontmatter: false,
  translate_frontmatter_keys: [],
}

const setup = ({
  markdownOverrides = {},
  currentProjectTemplateChanged = false,
} = {}) => {
  const modifyingCurrentTemplate = jest.fn()
  const currentTemplate = {
    id: 1,
    markdown: {...defaultMarkdown, ...markdownOverrides},
  }

  const utils = render(
    <FiltersParamsContext.Provider
      value={{
        currentTemplate,
        currentProjectTemplateChanged,
        modifyingCurrentTemplate,
      }}
    >
      <Markdown />
    </FiltersParamsContext.Provider>,
  )
  return {modifyingCurrentTemplate, currentTemplate, ...utils}
}

const lastUpdate = (modifyingCurrentTemplate, currentTemplate) =>
  modifyingCurrentTemplate.mock.calls[
    modifyingCurrentTemplate.mock.calls.length - 1
  ][0](currentTemplate).markdown

// the frontmatter switch is the only checkbox in the section
const toggle = () => screen.getByRole('checkbox')

describe('Markdown', () => {
  test('renders the frontmatter options with the final copy', () => {
    setup()

    expect(screen.getByText('Translate frontmatter')).toBeInTheDocument()
    expect(
      screen.getByText(
        'Choose whether to translate text contained in the frontmatter block at the beginning of the file.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByText('Translatable frontmatter keys'),
    ).toBeInTheDocument()
    expect(
      screen.getByText(/Choose which frontmatter keys should be translated/),
    ).toBeInTheDocument()
    expect(screen.getByText('except')).toBeInTheDocument()
  })

  test('defaults to extraction off with the keys field disabled', () => {
    setup()

    expect(toggle()).not.toBeChecked()
    expect(
      screen.getByTestId('radio-option-translate_frontmatter_keys'),
    ).toBeChecked()
    expect(
      screen.getByTestId('radio-option-translate_frontmatter_keys'),
    ).toBeDisabled()
    expect(
      screen.getByTestId('radio-option-do_not_translate_frontmatter_keys'),
    ).toBeDisabled()
    expect(screen.getByTestId('email-input')).toBeDisabled()
  })

  test('turning extraction on enables the keys field and reports it', async () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup()

    fireEvent.click(toggle())

    await waitFor(() => expect(screen.getByTestId('email-input')).toBeEnabled())
    expect(
      screen.getByTestId('radio-option-do_not_translate_frontmatter_keys'),
    ).toBeEnabled()
    expect(lastUpdate(modifyingCurrentTemplate, currentTemplate)).toEqual({
      extract_frontmatter: true,
      translate_frontmatter_keys: [],
    })
  })

  test('typing a translatable key reports it', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup({
      markdownOverrides: {extract_frontmatter: true},
    })

    await user.type(screen.getByTestId('email-input'), 'title,')

    await waitFor(() =>
      expect(lastUpdate(modifyingCurrentTemplate, currentTemplate)).toEqual({
        extract_frontmatter: true,
        translate_frontmatter_keys: ['title'],
      }),
    )
  })

  test('switching to Non-translatable sends only the non-translatable key list', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup({
      markdownOverrides: {
        extract_frontmatter: true,
        translate_frontmatter_keys: ['title'],
      },
    })

    await user.click(
      screen.getByTestId('radio-option-do_not_translate_frontmatter_keys'),
    )

    await waitFor(() =>
      expect(lastUpdate(modifyingCurrentTemplate, currentTemplate)).toEqual({
        extract_frontmatter: true,
        do_not_translate_frontmatter_keys: ['title'],
      }),
    )
  })

  test('shows Non-translatable when the template has do_not_translate_frontmatter_keys', () => {
    setup({
      markdownOverrides: {
        extract_frontmatter: true,
        translate_frontmatter_keys: undefined,
        do_not_translate_frontmatter_keys: ['slug'],
      },
    })

    expect(
      screen.getByTestId('radio-option-do_not_translate_frontmatter_keys'),
    ).toBeChecked()
    expect(screen.getByText('slug')).toBeInTheDocument()
  })

  test('turning extraction off keeps the keys, read-only', async () => {
    const {modifyingCurrentTemplate, currentTemplate} = setup({
      markdownOverrides: {
        extract_frontmatter: true,
        translate_frontmatter_keys: ['title'],
      },
    })

    fireEvent.click(toggle())

    await waitFor(() =>
      expect(screen.getByTestId('email-input')).toBeDisabled(),
    )
    expect(screen.getByText('title')).toBeInTheDocument()
    expect(lastUpdate(modifyingCurrentTemplate, currentTemplate)).toEqual({
      extract_frontmatter: false,
      translate_frontmatter_keys: ['title'],
    })
  })

  test('a key keeps its padding', async () => {
    const user = userEvent.setup()
    const {modifyingCurrentTemplate, currentTemplate} = setup({
      markdownOverrides: {extract_frontmatter: true},
    })

    await user.type(screen.getByTestId('email-input'), ' my key ')
    await user.keyboard('{Enter}')

    await waitFor(() =>
      expect(
        lastUpdate(modifyingCurrentTemplate, currentTemplate)
          .translate_frontmatter_keys,
      ).toEqual([' my key ']),
    )
  })

  // the settings panel back-fills a missing block from the default template
  // (normalizeTemplatesWithNullProps); the section must not crash without it
  test('renders a template that has no markdown block', () => {
    const modifyingCurrentTemplate = jest.fn()
    render(
      <FiltersParamsContext.Provider
        value={{
          currentTemplate: {id: 1},
          currentProjectTemplateChanged: false,
          modifyingCurrentTemplate,
        }}
      >
        <Markdown />
      </FiltersParamsContext.Provider>,
    )

    expect(toggle()).not.toBeChecked()
    expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
  })

  test('does not call modifyingCurrentTemplate when nothing changed', () => {
    const {modifyingCurrentTemplate} = setup()

    expect(modifyingCurrentTemplate).not.toHaveBeenCalled()
  })
})
