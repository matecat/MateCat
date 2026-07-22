import React from 'react'
import {render, screen, fireEvent, act} from '@testing-library/react'
import {fromJS} from 'immutable'
import {ModifyTeam} from './ModifyTeam'
import ManageActions from '../../actions/ManageActions'
import ModalsActions from '../../actions/ModalsActions'
import UserStore from '../../stores/UserStore'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'

jest.mock('../../actions/ManageActions')
jest.mock('../../actions/ModalsActions')
jest.mock('../../stores/UserStore')
jest.mock('../common/EmailsBadge/EmailsBadge', () => ({
  SPECIALS_SEPARATORS: {EnterKey: 'Enter'},
  EmailsBadge: ({onChange}) => (
    <button
      data-testid="emails-badge-mock"
      onClick={() => onChange(['new@member.com'])}
    >
      add emails
    </button>
  ),
}))

const buildTeam = (overrides = {}) =>
  fromJS({
    id: 1,
    name: 'Team Rocket',
    type: 'team',
    pending_invitations: ['pending@member.com'],
    members: [
      {
        user: {
          uid: 10,
          first_name: 'John',
          last_name: 'Doe',
          email: 'john@doe.com',
        },
      },
      {
        user: {
          uid: 20,
          first_name: 'Jane',
          last_name: 'Smith',
          email: 'jane@smith.com',
        },
        user_metadata: {gplus_picture: 'http://example.com/pic.png'},
      },
    ],
    ...overrides,
  })

const renderModifyTeam = (team = buildTeam(), currentUid = 10) =>
  render(
    <ApplicationWrapperContext.Provider
      value={{userInfo: {user: {uid: currentUid}}}}
    >
      <ModifyTeam team={team} />
    </ApplicationWrapperContext.Provider>,
  )

afterEach(() => jest.clearAllMocks())

test('renders team name, member list and pending invitations', () => {
  renderModifyTeam()

  expect(screen.getByText('Team Rocket')).toBeInTheDocument()
  expect(screen.getByText('John Doe')).toBeInTheDocument()
  expect(screen.getByText('Jane Smith')).toBeInTheDocument()
  expect(screen.getByText('pending@member.com')).toBeInTheDocument()
  expect(screen.getByText('Pending user')).toBeInTheDocument()
})

test('editing the team name and confirming calls ManageActions.changeTeamName', () => {
  const team = buildTeam()
  renderModifyTeam(team)

  fireEvent.click(document.querySelector('.button-edit'))
  const input = screen.getByDisplayValue('Team Rocket')
  fireEvent.change(input, {target: {value: 'New Team Name'}})
  fireEvent.click(screen.getByText('Confirm'))

  expect(ManageActions.changeTeamName).toHaveBeenCalledWith(
    team.toJS(),
    'New Team Name',
  )
  expect(screen.getByText('New Team Name')).toBeInTheDocument()
})

test('canceling the team name edit restores the original value without saving', () => {
  renderModifyTeam()

  fireEvent.click(document.querySelector('.button-edit'))
  const input = screen.getByDisplayValue('Team Rocket')
  fireEvent.change(input, {target: {value: 'Discarded'}})

  // The cancel icon-button is rendered right after the Confirm button.
  fireEvent.click(screen.getByText('Confirm').nextSibling)

  expect(ManageActions.changeTeamName).not.toHaveBeenCalled()
  expect(screen.getByText('Team Rocket')).toBeInTheDocument()
})

test('pressing Enter while editing the team name saves it', () => {
  const team = buildTeam()
  renderModifyTeam(team)

  fireEvent.click(document.querySelector('.button-edit'))
  const input = screen.getByDisplayValue('Team Rocket')
  fireEvent.change(input, {target: {value: 'Enter Saved'}})
  fireEvent.keyDown(input, {key: 'Enter'})

  expect(ManageActions.changeTeamName).toHaveBeenCalledWith(
    team.toJS(),
    'Enter Saved',
  )
})

test('inviting members calls ManageActions.addUserToTeam and resets the field', () => {
  const team = buildTeam()
  renderModifyTeam(team)

  fireEvent.click(screen.getByTestId('emails-badge-mock'))
  fireEvent.click(screen.getByText('Invite members'))

  expect(ManageActions.addUserToTeam).toHaveBeenCalledWith(team, [
    'new@member.com',
  ])
})

test('the invite button is disabled until a valid email is added', () => {
  renderModifyTeam()
  expect(screen.getByText('Invite members')).toBeDisabled()

  fireEvent.click(screen.getByTestId('emails-badge-mock'))
  expect(screen.getByText('Invite members')).toBeEnabled()
})

test('personal teams hide the manage-members / invite section', () => {
  renderModifyTeam(buildTeam({type: 'personal'}))

  expect(screen.queryByTestId('emails-badge-mock')).not.toBeInTheDocument()
  expect(screen.queryByText('Invite members')).not.toBeInTheDocument()
})

test('resending an invite marks it as sent', () => {
  const team = buildTeam()
  renderModifyTeam(team)

  fireEvent.click(screen.getByText('Resend Invite'))

  expect(ManageActions.addUserToTeam).toHaveBeenCalledWith(
    team,
    'pending@member.com',
  )
  expect(screen.getByText('Invite sent')).toBeInTheDocument()
  expect(screen.queryByText('Resend Invite')).not.toBeInTheDocument()
})

test('searching filters the member list and shows "No results!" when nothing matches', () => {
  renderModifyTeam()

  const search = screen.getByPlaceholderText('Search Member')
  fireEvent.change(search, {target: {value: 'Jane'}})

  expect(screen.queryByText('John Doe')).not.toBeInTheDocument()
  expect(screen.getByText('Jane Smith')).toBeInTheDocument()

  fireEvent.change(search, {target: {value: 'nobody-matches'}})
  expect(screen.getByText('No results!')).toBeInTheDocument()
})

test('clicking Remove then Confirm removes another member without closing the modal', () => {
  const team = buildTeam()
  renderModifyTeam(team, 10)

  const removeButtons = screen.getAllByText('Remove')
  fireEvent.click(removeButtons[1]) // Jane Smith, uid 20

  expect(
    screen.getByText('Are you sure you want to remove this user?'),
  ).toBeInTheDocument()

  fireEvent.click(screen.getByText('Confirm'))

  expect(ManageActions.removeUserFromTeam).toHaveBeenCalled()
  expect(ModalsActions.onCloseModal).not.toHaveBeenCalled()
})

test('removing yourself closes the modal and shows the leave-team message', () => {
  const team = buildTeam()
  renderModifyTeam(team, 10)

  const removeButtons = screen.getAllByText('Remove')
  fireEvent.click(removeButtons[0]) // John Doe, uid 10 === current user

  expect(
    screen.getByText('Are you sure you want to leave this team?'),
  ).toBeInTheDocument()

  fireEvent.click(screen.getByText('Confirm'))

  expect(ManageActions.removeUserFromTeam).toHaveBeenCalled()
  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})

test('shows the last-member deletion warning when the team has a single member', () => {
  const team = buildTeam({
    members: [
      {
        user: {
          uid: 10,
          first_name: 'John',
          last_name: 'Doe',
          email: 'john@doe.com',
        },
      },
    ],
  })
  renderModifyTeam(team, 10)

  fireEvent.click(screen.getByText('Remove'))

  expect(
    screen.getByText(
      'By removing the last member the team will be deleted. All projects will be moved to your Personal area.',
    ),
  ).toBeInTheDocument()
})

test('canceling a remove-member confirmation clears the pending state', () => {
  renderModifyTeam()

  const removeButtons = screen.getAllByText('Remove')
  fireEvent.click(removeButtons[0])
  expect(screen.getByText('Confirm')).toBeInTheDocument()

  fireEvent.click(screen.getByText('Confirm').nextSibling)

  expect(ManageActions.removeUserFromTeam).not.toHaveBeenCalled()
  expect(screen.getAllByText('Remove').length).toBe(2)
})

test('clicking Close calls ModalsActions.onCloseModal', () => {
  renderModifyTeam()

  fireEvent.click(screen.getByText('Close'))

  expect(ModalsActions.onCloseModal).toHaveBeenCalledTimes(1)
})

test('re-renders when UserStore emits an update for this team', () => {
  const team = buildTeam()
  renderModifyTeam(team, 10)

  expect(UserStore.addListener).toHaveBeenCalled()
  const [, listener] = UserStore.addListener.mock.calls.find(
    ([eventName]) => eventName === 'UPDATE_TEAM',
  )

  const updatedTeam = buildTeam({
    members: [
      ...team.get('members').toJS(),
      {
        user: {
          uid: 30,
          first_name: 'New',
          last_name: 'Member',
          email: 'new@member.com',
        },
      },
    ],
  })
  act(() => listener(updatedTeam))

  expect(screen.getByText('New Member')).toBeInTheDocument()
})
