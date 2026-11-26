import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import LanguageSelectorSearch from './LanguageSelectorSearch'

const languages = [
  {code: 'it-IT', id: 'it-IT', name: 'Italian'},
  {code: 'fr-FR', id: 'fr-FR', name: 'French'},
]

const renderComponent = (overrides = {}) => {
  const props = {
    selectedLanguages: languages,
    querySearch: '',
    onDeleteLanguage: jest.fn(),
    onQueryChange: jest.fn(),
    ...overrides,
  }
  const utils = render(<LanguageSelectorSearch {...props} />)
  return {...utils, props}
}

describe('LanguageSelectorSearch', () => {
  // Must run first: componentWillUnmount removes a *new* arrow function
  // reference rather than the one added in componentDidMount, so the
  // document 'mousedown' listener always leaks. Firing mousedown more than
  // once across this file would re-trigger setState on earlier unmounted
  // instances, so this is the only test allowed to dispatch it.
  test('resets highlight when clicking outside (document mousedown)', () => {
    const {container} = renderComponent()
    const input = screen.getByPlaceholderText('Search...')

    fireEvent.keyDown(input, {key: 'Backspace'})
    expect(
      container.querySelector('.language-search-tag.highlightDelete'),
    ).toBeInTheDocument()

    fireEvent.mouseDown(document)

    expect(
      container.querySelector('.language-search-tag.highlightDelete'),
    ).not.toBeInTheDocument()
  })

  test('renders a tag for each selected language', () => {
    renderComponent()
    expect(screen.getByText('Italian')).toBeInTheDocument()
    expect(screen.getByText('French')).toBeInTheDocument()
  })

  test('renders no tags when there are no selected languages', () => {
    const {container} = renderComponent({selectedLanguages: []})
    expect(container.querySelectorAll('.language-search-tag').length).toBe(0)
  })

  test('typing in the search input calls onQueryChange', () => {
    const {props} = renderComponent()
    const input = screen.getByPlaceholderText('Search...')
    fireEvent.change(input, {target: {value: 'ita'}})
    expect(props.onQueryChange).toHaveBeenCalledWith('ita')
  })

  test('clicking the remove button on a tag deletes that language', () => {
    const {props} = renderComponent()

    fireEvent.click(screen.getByRole('button', {name: 'Remove French'}))

    expect(props.onDeleteLanguage).toHaveBeenCalledWith(languages[1])
  })

  test('the remove button is reachable and works from the keyboard without confirming the modal', async () => {
    const user = userEvent.setup()
    const {props} = renderComponent()

    // The input is focused on mount; the remove buttons sit before it
    await user.tab({shift: true})
    expect(screen.getByRole('button', {name: 'Remove French'})).toHaveFocus()

    const documentKeyDown = jest.fn()
    document.addEventListener('keydown', documentKeyDown)
    await user.keyboard('{Enter}')

    document.removeEventListener('keydown', documentKeyDown)
    expect(props.onDeleteLanguage).toHaveBeenCalledWith(languages[1])
    // The modal confirms on a document Enter, so it must not see this one
    expect(documentKeyDown).not.toHaveBeenCalled()
  })

  test('Enter, Tab and arrow keys in the search input reach the modal untouched', () => {
    renderComponent()
    const input = screen.getByPlaceholderText('Search...')
    const seen = []
    const documentKeyDown = (event) =>
      seen.push([event.key, event.defaultPrevented])
    document.addEventListener('keydown', documentKeyDown)

    fireEvent.keyDown(input, {key: 'Enter'})
    fireEvent.keyDown(input, {key: 'Tab'})
    fireEvent.keyDown(input, {key: 'ArrowDown'})

    document.removeEventListener('keydown', documentKeyDown)
    expect(seen).toEqual([
      ['Enter', false],
      ['Tab', false],
      ['ArrowDown', false],
    ])
  })

  test('backspace with text in the input edits the text instead of highlighting a tag', () => {
    const {container, props} = renderComponent({querySearch: 'ita'})
    const input = screen.getByPlaceholderText('Search...')

    const notCancelled = fireEvent.keyDown(input, {key: 'Backspace'})

    expect(notCancelled).toBe(true)
    expect(
      container.querySelector('.language-search-tag.highlightDelete'),
    ).not.toBeInTheDocument()
    expect(props.onDeleteLanguage).not.toHaveBeenCalled()
  })

  test('the search input is focused on mount and when the tag area is clicked', () => {
    renderComponent()
    const input = screen.getByPlaceholderText('Search...')
    expect(input).toHaveFocus()

    input.blur()
    fireEvent.click(screen.getByText('Italian'))

    expect(input).toHaveFocus()
  })

  test('first backspace on an empty input highlights the last tag without deleting it', () => {
    const {container, props} = renderComponent()
    const input = screen.getByPlaceholderText('Search...')

    fireEvent.keyDown(input, {key: 'Backspace'})

    const highlighted = container.querySelector(
      '.language-search-tag.highlightDelete',
    )
    expect(highlighted).toBeInTheDocument()
    expect(highlighted).toHaveTextContent('French')
    expect(props.onDeleteLanguage).not.toHaveBeenCalled()
  })

  test('second consecutive backspace deletes the last selected language', () => {
    const {container, props} = renderComponent()
    const input = screen.getByPlaceholderText('Search...')

    fireEvent.keyDown(input, {key: 'Backspace'})
    fireEvent.keyDown(input, {key: 'Backspace'})

    expect(props.onDeleteLanguage).toHaveBeenCalledWith(languages[1])
    expect(
      container.querySelector('.language-search-tag.highlightDelete'),
    ).not.toBeInTheDocument()
  })

  test('highlight resets when the querySearch prop changes', () => {
    const {container, rerender, props} = renderComponent()
    const input = screen.getByPlaceholderText('Search...')

    fireEvent.keyDown(input, {key: 'Backspace'})
    expect(
      container.querySelector('.language-search-tag.highlightDelete'),
    ).toBeInTheDocument()

    rerender(<LanguageSelectorSearch {...props} querySearch="f" />)

    expect(
      container.querySelector('.language-search-tag.highlightDelete'),
    ).not.toBeInTheDocument()
  })
})
