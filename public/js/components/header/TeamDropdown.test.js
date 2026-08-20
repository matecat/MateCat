import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'

import {TeamDropdown} from './TeamDropdown'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import ManageActions from '../../actions/ManageActions'
import UserActions from '../../actions/UserActions'
import ModalsActions from '../../actions/ModalsActions'

jest.mock('../../actions/ManageActions')
jest.mock('../../actions/UserActions')
jest.mock('../../actions/ModalsActions')

class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

window.ResizeObserver = ResizeObserver
window.scrollTo = jest.fn()

const teams = [
  {id: 1, name: 'Personal', type: 'personal', isSelected: false},
  {id: 2, name: 'Test Team', type: 'general', isSelected: true},
]

const renderTeamDropdown = ({
  setUserInfo = jest.fn(),
  props = {},
  userInfo = {teams},
} = {}) =>
  render(
    <ApplicationWrapperContext.Provider value={{userInfo, setUserInfo}}>
      <TeamDropdown {...props} />
    </ApplicationWrapperContext.Provider>,
  )

const openDropdown = () => userEvent.click(screen.getByRole('button'))

test('Shows the selected team name on the trigger button', () => {
  renderTeamDropdown()

  expect(screen.getByTestId('team-select')).toHaveTextContent('Test Team')
})

test('Falls back to "Choose team" when no team is selected', () => {
  renderTeamDropdown({
    userInfo: {teams: teams.map((team) => ({...team, isSelected: false}))},
  })

  expect(screen.getByTestId('team-select')).toHaveTextContent('Choose team')
})

test('Opening the dropdown lists every team and the create action', async () => {
  renderTeamDropdown()

  await openDropdown()

  expect(screen.getByText('Personal')).toBeInTheDocument()
  expect(screen.getByText('Create new team')).toBeInTheDocument()
})

test('Hides the create action when showModals is false', async () => {
  renderTeamDropdown({props: {showModals: false}})

  await openDropdown()

  expect(screen.queryByText('Create new team')).not.toBeInTheDocument()
})

test('Clicking "Create new team" opens the create team modal', async () => {
  renderTeamDropdown()

  await openDropdown()
  await userEvent.click(screen.getByText('Create new team'))

  expect(ModalsActions.openCreateTeamModal).toHaveBeenCalled()
})

test('Selecting a team updates the context and changes the team (manage mode)', async () => {
  const setUserInfo = jest.fn()
  renderTeamDropdown({setUserInfo})

  await openDropdown()
  await userEvent.click(screen.getByText('Personal'))

  expect(ManageActions.changeTeam).toHaveBeenCalledWith(teams[0])
  expect(setUserInfo).toHaveBeenCalled()

  const updater = setUserInfo.mock.calls[0][0]
  const updated = updater({teams})
  expect(updated.teams.find(({id}) => id === 1).isSelected).toBe(true)
  expect(updated.teams.find(({id}) => id === 2).isSelected).toBe(false)
})

test('Selecting a team outside manage mode uses the upload page action', async () => {
  renderTeamDropdown({props: {isManage: false}})

  await openDropdown()
  await userEvent.click(screen.getByText('Personal'))

  expect(UserActions.changeTeamFromUploadPage).toHaveBeenCalledWith(teams[0])
  expect(ManageActions.changeTeam).not.toHaveBeenCalled()
})

test('Clicking the settings icon opens the modify team modal without selecting the team', async () => {
  renderTeamDropdown()

  await openDropdown()

  const settingsIcon = document.querySelector('.container-icon-settings')
  await userEvent.click(settingsIcon)

  expect(ManageActions.openModifyTeamModal).toHaveBeenCalledWith(teams[1])
  expect(ManageActions.changeTeam).not.toHaveBeenCalled()
})

test('Closes the dropdown when clicking outside of it', async () => {
  renderTeamDropdown()

  await openDropdown()
  expect(screen.getByTestId('team-select').className).toContain('open')

  await userEvent.click(document.body)

  expect(screen.getByTestId('team-select').className).not.toContain('open')
})
