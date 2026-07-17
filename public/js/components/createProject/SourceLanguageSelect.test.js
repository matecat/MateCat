import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {SourceLanguageSelect} from './SourceLanguageSelect'
import {CreateProjectContext} from './CreateProjectContext'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'

// Dropdown.js's getFilteredOptions() filters out any option without an `id`
// (see public/js/components/common/Dropdown.js:87-92). In production,
// NewProject.js's retrieveSupportedLanguages() decorates every language with
// `id: lang.code` before it reaches context, so the mock must do the same or
// the dropdown renders zero options.
const languages = [
  {id: 'en-US', code: 'en-US', name: 'English (US)'},
  {id: 'it-IT', code: 'it-IT', name: 'Italian'},
]

const renderWithContext = (
  createProjectOverrides = {},
  appOverrides = {isUserLogged: true},
) => {
  const createProjectValue = {
    SELECT_HEIGHT: 300,
    languages,
    sourceLang: {},
    changeSourceLanguage: jest.fn(),
    projectTemplates: [{id: 1}],
    ...createProjectOverrides,
  }
  return {
    ...render(
      <ApplicationWrapperContext.Provider value={appOverrides}>
        <CreateProjectContext.Provider value={createProjectValue}>
          <SourceLanguageSelect />
        </CreateProjectContext.Provider>
      </ApplicationWrapperContext.Provider>,
    ),
    createProjectValue,
  }
}

describe('SourceLanguageSelect', () => {
  test('renders the "From" label', () => {
    renderWithContext()
    expect(screen.getByText('From')).toBeInTheDocument()
  })

  test('is disabled when the user is not logged in', () => {
    const {container} = renderWithContext({}, {isUserLogged: false})
    expect(container.querySelector('.select--is-disabled')).toBeInTheDocument()
  })

  test('is disabled when there are no project templates', () => {
    const {container} = renderWithContext({projectTemplates: []})
    expect(container.querySelector('.select--is-disabled')).toBeInTheDocument()
  })

  test('selecting a language calls changeSourceLanguage', () => {
    const {container, createProjectValue} = renderWithContext()
    fireEvent.click(container.querySelector('.select'))
    fireEvent.click(screen.getByText('Italian'))
    expect(createProjectValue.changeSourceLanguage).toHaveBeenCalledWith(
      languages[1],
    )
  })

  test('shows the active option code and name', () => {
    renderWithContext({sourceLang: languages[0]})
    expect(screen.getByText('English (US)')).toBeInTheDocument()
  })
})
