import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {TargetLanguagesSelect} from './TargetLanguagesSelect'
import {CreateProjectContext} from './CreateProjectContext'

const renderWithContext = (overrides = {}) => {
  const contextValue = {
    targetLangs: [],
    setIsOpenMultiselectLanguages: jest.fn(),
    languages: [{code: 'it-IT', name: 'Italian'}],
    projectTemplates: [{id: 1}],
    ...overrides,
  }
  return {
    ...render(
      <CreateProjectContext.Provider value={contextValue}>
        <TargetLanguagesSelect />
      </CreateProjectContext.Provider>,
    ),
    contextValue,
  }
}

describe('TargetLanguagesSelect', () => {
  test('renders a disabled placeholder when there are no project templates', () => {
    const {container} = renderWithContext({projectTemplates: []})
    expect(container.querySelector('.select--is-disabled')).toBeInTheDocument()
  })

  test('shows a single selected language name', () => {
    renderWithContext({targetLangs: [{id: 1, name: 'Italian'}]})
    expect(screen.getByText('Italian')).toBeInTheDocument()
  })

  test('shows a count when multiple languages are selected', () => {
    renderWithContext({
      targetLangs: [
        {id: 1, name: 'Italian'},
        {id: 2, name: 'French'},
      ],
    })
    expect(screen.getByText('2 languages')).toBeInTheDocument()
  })

  test('sets an aria-label with all selected language names when more than one is selected', () => {
    const {container} = renderWithContext({
      targetLangs: [
        {id: 1, name: 'Italian'},
        {id: 2, name: 'French'},
      ],
    })
    expect(
      container.querySelector('[aria-label="Italian,French"]'),
    ).toBeInTheDocument()
  })

  test('clicking the wrapper opens the multiselect modal when languages exist', () => {
    const {container, contextValue} = renderWithContext()
    fireEvent.click(container.querySelector('#target-lang'))
    expect(contextValue.setIsOpenMultiselectLanguages).toHaveBeenCalledWith(true)
  })

  test('does not open the modal when there are no languages loaded', () => {
    const {container, contextValue} = renderWithContext({languages: []})
    fireEvent.click(container.querySelector('#target-lang'))
    expect(contextValue.setIsOpenMultiselectLanguages).not.toHaveBeenCalled()
  })
})
