import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {Team} from './Team'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'
import UserActions from '../../../../actions/UserActions'

jest.mock('../../../../actions/UserActions', () => ({
  getLastTeamSelected: jest.fn(),
}))

jest.mock('../../../common/Select', () => ({
  Select: ({onSelect, activeOption, options, isDisabled}) => (
    <div>
      <div data-testid="active-option">{activeOption?.name ?? ''}</div>
      <div data-testid="is-disabled">{String(isDisabled)}</div>
      {options?.map((opt) => (
        <button
          key={opt.id}
          data-testid={`option-${opt.id}`}
          onClick={() => onSelect(opt)}
        >
          {opt.name}
        </button>
      ))}
    </div>
  ),
}))

const renderTeam = (user, selectedTeam, setSelectedTeam = jest.fn()) =>
  render(
    <SettingsPanelContext.Provider value={{user}}>
      <CreateProjectContext.Provider
        value={{SELECT_HEIGHT: 200, selectedTeam, setSelectedTeam}}
      >
        <Team />
      </CreateProjectContext.Provider>
    </SettingsPanelContext.Provider>,
  )

describe('Team', () => {
  test('renders heading and description', () => {
    renderTeam(undefined, undefined)
    expect(screen.getByText('Team')).toBeInTheDocument()
    expect(
      screen.getByText('Select what team the project should be created in.'),
    ).toBeInTheDocument()
  })

  test('renders no team options and disables select when user is undefined', () => {
    renderTeam(undefined, undefined)
    expect(screen.getByTestId('is-disabled').textContent).toBe('true')
    expect(screen.queryByTestId('option-1')).not.toBeInTheDocument()
  })

  test('maps user.teams to id/name options, stringifying id', () => {
    const user = {teams: [{id: 1, name: 'Team A'}, {id: 2, name: 'Team B'}]}
    renderTeam(user, undefined)
    expect(screen.getByTestId('option-1')).toHaveTextContent('Team A')
    expect(screen.getByTestId('option-2')).toHaveTextContent('Team B')
  })

  test('disables select when the user only has one team', () => {
    const user = {teams: [{id: 1, name: 'Team A'}]}
    renderTeam(user, undefined)
    expect(screen.getByTestId('is-disabled').textContent).toBe('true')
  })

  test('enables select when the user has more than one team', () => {
    const user = {teams: [{id: 1, name: 'Team A'}, {id: 2, name: 'Team B'}]}
    renderTeam(user, undefined)
    expect(screen.getByTestId('is-disabled').textContent).toBe('false')
  })

  test('shows selectedTeam as active option', () => {
    const user = {teams: [{id: 1, name: 'Team A'}]}
    renderTeam(user, {id: '1', name: 'Team A'})
    expect(screen.getByTestId('active-option').textContent).toBe('Team A')
  })

  test('selecting a team calls setSelectedTeam with the option', () => {
    const user = {teams: [{id: 1, name: 'Team A'}, {id: 2, name: 'Team B'}]}
    const setSelectedTeam = jest.fn()
    renderTeam(user, undefined, setSelectedTeam)

    fireEvent.click(screen.getByTestId('option-2'))

    expect(setSelectedTeam).toHaveBeenCalledWith(
      expect.objectContaining({id: '2', name: 'Team B'}),
    )
  })

  test('on mount with user.teams, calls setSelectedTeam with UserActions.getLastTeamSelected result', () => {
    const user = {teams: [{id: 1, name: 'Team A'}, {id: 2, name: 'Team B'}]}
    const setSelectedTeam = jest.fn()
    UserActions.getLastTeamSelected.mockReturnValue({id: '2', name: 'Team B'})

    renderTeam(user, undefined, setSelectedTeam)

    expect(UserActions.getLastTeamSelected).toHaveBeenCalledWith([
      {id: '1', name: 'Team A'},
      {id: '2', name: 'Team B'},
    ])
    expect(setSelectedTeam).toHaveBeenCalledWith({id: '2', name: 'Team B'})
  })

  test('does not call setSelectedTeam on mount when user.teams is not an array', () => {
    const setSelectedTeam = jest.fn()
    renderTeam(undefined, undefined, setSelectedTeam)

    expect(setSelectedTeam).not.toHaveBeenCalled()
  })
})
