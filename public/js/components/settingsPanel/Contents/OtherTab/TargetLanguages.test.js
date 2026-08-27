import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {TargetLanguages} from './TargetLanguages'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

jest.mock('../../../common/Select', () => ({
  Select: ({options, activeOptions, onToggleOption, children}) => (
    <div>
      <div data-testid="active-options">
        {activeOptions?.map(({id}) => id).join(',')}
      </div>
      {options?.map((opt) => (
        <div key={opt.id}>
          <button
            data-testid={`toggle-${opt.id}`}
            onClick={() => onToggleOption(opt)}
          >
            toggle
          </button>
          {children && (
            <div data-testid={`row-${opt.id}`}>{children(opt).row}</div>
          )}
        </div>
      ))}
    </div>
  ),
}))

const languages = [
  {id: 'it-IT', code: 'it-IT', name: 'Italian'},
  {id: 'fr-FR', code: 'fr-FR', name: 'French'},
]

const renderTargetLanguages = (targetLangs, setTargetLangs = jest.fn()) =>
  render(
    <CreateProjectContext.Provider
      value={{SELECT_HEIGHT: 200, languages, targetLangs, setTargetLangs}}
    >
      <TargetLanguages />
    </CreateProjectContext.Provider>,
  )

describe('TargetLanguages', () => {
  test('renders heading and description', () => {
    renderTargetLanguages([])
    expect(screen.getByText('Target language(s)')).toBeInTheDocument()
    expect(
      screen.getByText(
        'Select one or more target languages for your project.',
      ),
    ).toBeInTheDocument()
  })

  test('renders the row content for each language option', () => {
    renderTargetLanguages([])
    expect(screen.getByTestId('row-it-IT')).toHaveTextContent('Italian')
    expect(screen.getByTestId('row-it-IT')).toHaveTextContent('it-IT')
  })

  test('shows currently selected target languages as active options', () => {
    renderTargetLanguages([languages[0]])
    expect(screen.getByTestId('active-options').textContent).toBe('it-IT')
  })

  test('toggling an unselected option adds it to targetLangs', () => {
    const setTargetLangs = jest.fn()
    renderTargetLanguages([], setTargetLangs)

    fireEvent.click(screen.getByTestId('toggle-fr-FR'))

    expect(setTargetLangs).toHaveBeenCalledWith([languages[1]])
  })

  test('toggling an already-selected option removes it from targetLangs', () => {
    const setTargetLangs = jest.fn()
    renderTargetLanguages([languages[0], languages[1]], setTargetLangs)

    fireEvent.click(screen.getByTestId('toggle-it-IT'))

    expect(setTargetLangs).toHaveBeenCalledWith([languages[1]])
  })
})
