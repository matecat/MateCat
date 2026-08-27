import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'

// `LanguageSelector.js` computes its recently-used-languages localStorage key
// from `config.userMail` as a module-level constant. Since Babel hoists
// `import` declarations above other top-level statements, a static import
// here would evaluate the module before `global.config.userMail` below is
// set. Deferring to `require` after the assignment ensures the module reads
// the real value.
global.config = {...global.config, userMail: 'user@example.com'}

const {
  default: LanguageSelector,
  setRecentlyUsedLanguages,
} = require('./LanguageSelector')

const languagesList = [
  {code: 'it-IT', id: 'it-IT', name: 'Italian'},
  {code: 'fr-FR', id: 'fr-FR', name: 'French'},
  {code: 'de-DE', id: 'de-DE', name: 'German'},
  {code: 'es-ES', id: 'es-ES', name: 'Spanish'},
]

const renderComponent = (overrides = {}) => {
  const props = {
    languagesList,
    selectedLanguagesFromDropdown: ['fr-FR'],
    fromLanguage: 'it-IT',
    onClose: jest.fn(),
    onConfirm: jest.fn(),
    ...overrides,
  }
  return {...render(<LanguageSelector {...props} />), props}
}

describe('LanguageSelector', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  test('renders the from-language name and the target-language search', () => {
    const {container} = renderComponent()
    expect(container.querySelector('.language-from').textContent).toContain(
      'Italian',
    )
    expect(
      container.querySelector('.language-search .tag').textContent,
    ).toContain('French')
  })

  test('shows the selected-language count in the footer', () => {
    renderComponent()
    expect(screen.getByText('1')).toBeInTheDocument()
    expect(screen.getByText('Language selected')).toBeInTheDocument()
  })

  test('pluralizes the footer label when 0 or more than 1 language is selected', () => {
    renderComponent({selectedLanguagesFromDropdown: ['fr-FR', 'de-DE']})
    expect(screen.getByText('Languages selected')).toBeInTheDocument()
  })

  test('clicking the modal overlay calls onClose', () => {
    const {props, container} = renderComponent()
    fireEvent.click(container.querySelector('.matecat-modal'))
    expect(props.onClose).toHaveBeenCalled()
  })

  test('clicking inside the modal content does not call onClose', () => {
    const {props, container} = renderComponent()
    fireEvent.click(container.querySelector('.matecat-modal-content'))
    expect(props.onClose).not.toHaveBeenCalled()
  })

  test('pressing Escape calls onClose', () => {
    const {props} = renderComponent()
    fireEvent.keyDown(document, {keyCode: 27})
    expect(props.onClose).toHaveBeenCalled()
  })

  test('pressing Enter with no active search calls onConfirm with the selected languages', () => {
    const {props} = renderComponent()
    fireEvent.keyDown(document, {key: 'Enter'})
    expect(props.onConfirm).toHaveBeenCalledWith([
      {code: 'fr-FR', id: 'fr-FR', name: 'French'},
    ])
  })

  test('clicking "Confirm" calls onConfirm with the current selection', () => {
    const {props} = renderComponent()
    fireEvent.click(screen.getByRole('button', {name: 'Confirm'}))
    expect(props.onConfirm).toHaveBeenCalledWith([
      {code: 'fr-FR', id: 'fr-FR', name: 'French'},
    ])
  })

  test('the uncheck-all icon appears only when at least one language is selected, and clicking it resets the selection', () => {
    const {container} = renderComponent()
    expect(container.querySelector('.uncheck-all')).toBeInTheDocument()
    fireEvent.click(container.querySelector('.uncheck-all'))
    expect(container.querySelector('.uncheck-all')).not.toBeInTheDocument()
  })

  test('toggling a language on adds it to the selection', () => {
    const {container} = renderComponent({selectedLanguagesFromDropdown: []})
    const item = Array.from(container.querySelectorAll('.lang-item')).find(
      (el) => el.textContent.includes('German'),
    )
    fireEvent.click(item)
    expect(container.querySelector('.badge').textContent).toBe('1')
  })

  test('shows recently used languages from localStorage and applies them on click', () => {
    setRecentlyUsedLanguages([{code: 'de-DE', name: 'German'}])
    renderComponent({selectedLanguagesFromDropdown: []})
    expect(screen.getByText('Recently used:')).toBeInTheDocument()
    fireEvent.click(screen.getByText('German', {selector: '.language-name'}))
    expect(screen.getByText('1')).toBeInTheDocument()
  })

  test('shows the "All languages" reset button while a search query is active, and it clears the query', () => {
    renderComponent()
    fireEvent.change(screen.getByPlaceholderText('Search...'), {
      target: {value: 'ger'},
    })
    expect(screen.getByText('All languages')).toBeInTheDocument()
    fireEvent.click(screen.getByText('All languages'))
    expect(screen.queryByText('All languages')).not.toBeInTheDocument()
  })
})

describe('setRecentlyUsedLanguages', () => {
  const KEY = 'target_languages_recently_used-user@example.com'

  beforeEach(() => localStorage.clear())

  test('does nothing when given an empty array', () => {
    setRecentlyUsedLanguages([])
    expect(localStorage.getItem(KEY)).toBeNull()
  })

  test('stores a new combination', () => {
    setRecentlyUsedLanguages([{id: 'it', code: 'it-IT', name: 'Italian'}])
    const stored = JSON.parse(localStorage.getItem(KEY))
    expect(stored).toHaveLength(1)
  })

  test('caps the stored history at 3 entries, dropping the oldest', () => {
    setRecentlyUsedLanguages([{id: 'a', code: 'a', name: 'A'}])
    setRecentlyUsedLanguages([{id: 'b', code: 'b', name: 'B'}])
    setRecentlyUsedLanguages([{id: 'c', code: 'c', name: 'C'}])
    setRecentlyUsedLanguages([{id: 'd', code: 'd', name: 'D'}])
    const stored = JSON.parse(localStorage.getItem(KEY))
    expect(stored).toHaveLength(3)
    expect(stored.some((list) => list.some((l) => l.id === 'a'))).toBe(false)
    expect(stored.some((list) => list.some((l) => l.id === 'd'))).toBe(true)
  })

  test('replaces an existing identical combination rather than duplicating it', () => {
    setRecentlyUsedLanguages([{id: 'a', code: 'a', name: 'A'}])
    setRecentlyUsedLanguages([{id: 'a', code: 'a', name: 'A'}])
    const stored = JSON.parse(localStorage.getItem(KEY))
    expect(stored).toHaveLength(1)
  })
})
