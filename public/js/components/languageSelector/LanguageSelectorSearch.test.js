import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
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
    expect(container.querySelector('.tag.highlightDelete')).toBeInTheDocument()

    fireEvent.mouseDown(document)

    expect(
      container.querySelector('.tag.highlightDelete'),
    ).not.toBeInTheDocument()
  })

  test('renders a tag for each selected language', () => {
    renderComponent()
    expect(screen.getByText('Italian')).toBeInTheDocument()
    expect(screen.getByText('French')).toBeInTheDocument()
  })

  test('renders no tags when there are no selected languages', () => {
    const {container} = renderComponent({selectedLanguages: []})
    expect(container.querySelectorAll('.tag').length).toBe(0)
  })

  test('typing in the search input calls onQueryChange', () => {
    const {props} = renderComponent()
    const input = screen.getByPlaceholderText('Search...')
    fireEvent.change(input, {target: {value: 'ita'}})
    expect(props.onQueryChange).toHaveBeenCalledWith('ita')
  })

  test('clicking the remove icon on a tag deletes that language', () => {
    const {container, props} = renderComponent()
    const removeLinks = container.querySelectorAll('.react-tagsinput-remove')
    expect(removeLinks.length).toBe(2)

    fireEvent.click(removeLinks[1])

    expect(props.onDeleteLanguage).toHaveBeenCalledWith(languages[1])
  })

  test('first backspace on an empty input highlights the last tag without deleting it', () => {
    const {container, props} = renderComponent()
    const input = screen.getByPlaceholderText('Search...')

    fireEvent.keyDown(input, {key: 'Backspace'})

    const highlighted = container.querySelector('.tag.highlightDelete')
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
    expect(container.querySelector('.tag.highlightDelete')).not.toBeInTheDocument()
  })

  test('highlight resets when the querySearch prop changes', () => {
    const {container, rerender, props} = renderComponent()
    const input = screen.getByPlaceholderText('Search...')

    fireEvent.keyDown(input, {key: 'Backspace'})
    expect(container.querySelector('.tag.highlightDelete')).toBeInTheDocument()

    rerender(<LanguageSelectorSearch {...props} querySearch="f" />)

    expect(container.querySelector('.tag.highlightDelete')).not.toBeInTheDocument()
  })
})
