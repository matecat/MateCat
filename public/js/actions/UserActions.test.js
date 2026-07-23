jest.mock('jquery', () => {
  const mockJQueryInstance = {show: jest.fn(), hide: jest.fn()}
  return jest.fn(() => mockJQueryInstance)
})
jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
}))
jest.mock('../constants/UserConstants', () => ({
  UPDATE_USER: 'UPDATE_USER',
  RENDER_TEAMS: 'RENDER_TEAMS',
  UPDATE_TEAM: 'UPDATE_TEAM',
  UPDATE_TEAMS: 'UPDATE_TEAMS',
  CHOOSE_TEAM: 'CHOOSE_TEAM',
  FORCE_RELOAD: 'FORCE_RELOAD',
}))
jest.mock('../api/getTeamMembers', () => ({
  getTeamMembers: jest.fn(),
}))
jest.mock('../stores/UserStore', () => ({
  getUser: jest.fn(),
}))

import UserActions from './UserActions'
import AppDispatcher from '../stores/AppDispatcher'
import {getTeamMembers} from '../api/getTeamMembers'
import UserStore from '../stores/UserStore'

describe('UserActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    localStorage.clear()
  })

  test('updateUser dispatches UPDATE_USER', () => {
    UserActions.updateUser({id: 1})

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_USER',
      user: {id: 1},
    })
  })

  test('updateUserName references an undefined TeamConstants (known bug) and throws', () => {
    // NOTE: UserActions.js:18 references `TeamConstants` which is never
    // imported in the source file. Calling this method throws a
    // ReferenceError. Flagging as a pre-existing bug rather than fixing it
    // silently, per task instructions.
    expect(() => UserActions.updateUserName({foo: 'bar'})).toThrow(
      ReferenceError,
    )
    expect(AppDispatcher.dispatch).not.toHaveBeenCalled()
  })

  test('renderTeams dispatches RENDER_TEAMS with teams and defaultTeam', () => {
    UserActions.renderTeams(['t1'], 't1')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RENDER_TEAMS',
      teams: ['t1'],
      defaultTeam: 't1',
    })
  })

  test('updateTeam fetches members and dispatches UPDATE_TEAM', async () => {
    getTeamMembers.mockResolvedValueOnce({
      members: ['m1'],
      pending_invitations: ['p1'],
    })
    const team = {id: 5}

    UserActions.updateTeam(team)
    await Promise.resolve()

    expect(getTeamMembers).toHaveBeenCalledWith(5)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_TEAM',
      team: {id: 5, members: ['m1'], pending_invitations: ['p1']},
    })
  })

  test('updateTeams dispatches UPDATE_TEAMS', () => {
    UserActions.updateTeams(['t1', 't2'])

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_TEAMS',
      teams: ['t1', 't2'],
    })
  })

  test('getAllTeams dispatches RENDER_TEAMS using UserStore teams', () => {
    UserStore.getUser.mockReturnValueOnce({teams: ['t1']})

    UserActions.getAllTeams()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RENDER_TEAMS',
      teams: ['t1'],
    })
  })

  test('selectTeam dispatches UPDATE_TEAM and CHOOSE_TEAM', () => {
    const team = {id: 9}

    UserActions.selectTeam(team)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_TEAM',
      team,
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CHOOSE_TEAM',
      teamId: 9,
    })
  })

  test('changeTeamFromUploadPage stores team and dispatches CHOOSE_TEAM', () => {
    jest.useFakeTimers()
    const team = {id: 3}

    UserActions.changeTeamFromUploadPage(team)

    expect(localStorage.getItem('defaultTeam')).toBe('3')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CHOOSE_TEAM',
      teamId: 3,
    })

    jest.runAllTimers()
    jest.useRealTimers()
  })

  test('getLastTeamSelected returns matching stored team', () => {
    localStorage.setItem('defaultTeam', '2')
    const teams = [{id: 1}, {id: 2}, {id: 3}]

    expect(UserActions.getLastTeamSelected(teams)).toEqual({id: 2})
  })

  test('getLastTeamSelected returns first team when stored id has no match', () => {
    localStorage.setItem('defaultTeam', '99')
    const teams = [{id: 1}, {id: 2}]

    expect(UserActions.getLastTeamSelected(teams)).toEqual({id: 1})
  })

  test('getLastTeamSelected returns first team when nothing stored', () => {
    const teams = [{id: 1}, {id: 2}]

    expect(UserActions.getLastTeamSelected(teams)).toEqual({id: 1})
  })

  test('setTeamInStorage stores teamId', () => {
    UserActions.setTeamInStorage(42)

    expect(localStorage.getItem('defaultTeam')).toBe('42')
  })

  test('forceReload dispatches FORCE_RELOAD', () => {
    UserActions.forceReload()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'FORCE_RELOAD',
    })
  })
})
