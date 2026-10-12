import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {TmGlossarySelect} from './TmGlossarySelect'
import {CreateProjectContext} from './CreateProjectContext'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'

const tmKeys = [
  {id: 1, name: 'My Private TM', key: 'key-1', isActive: false, r: true, w: true},
  {id: 2, name: 'Another TM', key: 'key-2', isActive: true, r: true, w: true},
]

const renderWithContext = (overrides = {}, appOverrides = {isUserLogged: true}) => {
  const contextValue = {
    SELECT_HEIGHT: 300,
    tmKeys,
    setOpenSettings: jest.fn(),
    modifyingCurrentTemplate: jest.fn(),
    projectTemplates: [{id: 1}],
    ...overrides,
  }
  return {
    ...render(
      <ApplicationWrapperContext.Provider value={appOverrides}>
        <CreateProjectContext.Provider value={contextValue}>
          <TmGlossarySelect />
        </CreateProjectContext.Provider>
      </ApplicationWrapperContext.Provider>,
    ),
    contextValue,
  }
}

describe('TmGlossarySelect', () => {
  test('renders the TM & TB label', () => {
    renderWithContext()
    expect(screen.getByText('TM & TB')).toBeInTheDocument()
  })

  test('is disabled when there are no tmKeys', () => {
    // `isDisabled` is driven by `!tmKeys`; an empty array is truthy in JS
    // (see TmGlossarySelect.js:46), so "no tmKeys" must be modeled as
    // undefined/null (data not loaded yet), not an empty array (data loaded,
    // zero private TMs — which is still interactive, see the
    // "no private resources" test below).
    const {container} = renderWithContext({tmKeys: undefined})
    expect(container.querySelector('.select--is-disabled')).toBeInTheDocument()
  })

  test('is disabled when the user is not logged in', () => {
    const {container} = renderWithContext({}, {isUserLogged: false})
    expect(container.querySelector('.select--is-disabled')).toBeInTheDocument()
  })

  test('shows the "no private resources" message when tmKeys is an empty array', () => {
    const {container} = renderWithContext({tmKeys: []})
    fireEvent.click(container.querySelector('.select'))
    expect(screen.getByText('You have no private resources')).toBeInTheDocument()
  })

  test('clicking CREATE RESOURCE opens settings and closes the dropdown', () => {
    const {container, contextValue} = renderWithContext()
    fireEvent.click(container.querySelector('.select'))
    fireEvent.click(screen.getByText('CREATE RESOURCE'))
    expect(contextValue.setOpenSettings).toHaveBeenCalledWith({isOpen: true})
  })

  test('toggling an inactive option activates it and calls modifyingCurrentTemplate', () => {
    const {container, contextValue} = renderWithContext()
    fireEvent.click(container.querySelector('.select'))
    fireEvent.click(screen.getByText('My Private TM'))
    expect(contextValue.modifyingCurrentTemplate).toHaveBeenCalled()
    const updater = contextValue.modifyingCurrentTemplate.mock.calls[0][0]
    const result = updater({tm: [{key: 'key-2'}]})
    expect(result.tm.some((tm) => tm.key === 'key-1')).toBe(true)
  })

  test('toggling an already-active option deactivates it', () => {
    // 'Another TM' (tmKeys[1], isActive: true) renders twice: once as the
    // closed select's selected-value label and once as a dropdown row, so
    // it can't be targeted with screen.getByText. Click the active row
    // (.dropdown__option--is-active-option) directly instead.
    const {container, contextValue} = renderWithContext()
    fireEvent.click(container.querySelector('.select'))
    fireEvent.click(container.querySelector('.dropdown__option--is-active-option'))
    const updater = contextValue.modifyingCurrentTemplate.mock.calls[0][0]
    const result = updater({tm: [{key: 'key-2'}]})
    expect(result.tm.some((tm) => tm.key === 'key-2')).toBe(false)
  })
})
