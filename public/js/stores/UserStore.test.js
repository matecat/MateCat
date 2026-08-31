import AppDispatcher from './AppDispatcher'
import UserStore from './UserStore'
import UserConstants from '../constants/UserConstants'
import ManageConstants from '../constants/ManageConstants'
import {fromJS} from 'immutable'

describe('UserStore', () => {
  beforeEach(() => {
    UserStore.teams = fromJS([])
    UserStore.selectedTeam = {}
    UserStore.userInfo = null
    jest.clearAllMocks()
  })

  test('updateTeams/getAllTeams stores and returns plain teams', () => {
    UserStore.updateTeams([{id: 1, name: 'A'}])

    expect(UserStore.getAllTeams()).toEqual([{id: 1, name: 'A'}])
  })

  test('addTeam appends a team to the list', () => {
    UserStore.updateTeams([{id: 1, name: 'A'}])
    UserStore.addTeam({id: 2, name: 'B'})

    expect(UserStore.getAllTeams()).toEqual([
      {id: 1, name: 'A'},
      {id: 2, name: 'B'},
    ])
  })

  test('updateUser stores the user and syncs teams when present', () => {
    UserStore.updateUser({
      user: {first_name: 'John', last_name: 'Doe'},
      teams: [{id: 1, name: 'A'}],
    })

    expect(UserStore.getUser().user.first_name).toBe('John')
    expect(UserStore.getAllTeams()).toEqual([{id: 1, name: 'A'}])
  })

  test('updateUser without teams does not touch the teams list', () => {
    UserStore.updateUser({user: {first_name: 'John', last_name: 'Doe'}})

    expect(UserStore.getAllTeams()).toEqual([])
  })

  test('updateUserName updates first and last name', () => {
    UserStore.userInfo = {user: {first_name: 'John', last_name: 'Doe'}}

    UserStore.updateUserName({firstName: 'Jane', lastName: 'Smith'})

    expect(UserStore.userInfo.user.first_name).toBe('Jane')
    expect(UserStore.userInfo.user.last_name).toBe('Smith')
  })

  test('updateTeam replaces the matching team', () => {
    UserStore.teams = fromJS([
      {id: 1, name: 'A'},
      {id: 2, name: 'B'},
    ])

    const result = UserStore.updateTeam({id: 2, name: 'B2'})

    expect(result.toJS()).toEqual({id: 2, name: 'B2'})
  })

  test('updateTeamName updates only the team name', () => {
    UserStore.teams = fromJS([{id: 1, name: 'A'}])

    const result = UserStore.updateTeamName({id: 1, name: 'A2'})

    expect(result.toJS()).toEqual({id: 1, name: 'A2'})
  })

  test('updateTeamMembers updates members and pending invitations', () => {
    UserStore.teams = fromJS([{id: 1, name: 'A'}])

    const result = UserStore.updateTeamMembers(
      fromJS({id: 1, name: 'A'}),
      [{id: 10}],
      [{id: 20}],
    )

    expect(result.toJS()).toEqual({
      id: 1,
      name: 'A',
      members: [{id: 10}],
      pending_invitations: [{id: 20}],
    })
  })

  test('removeTeam deletes a matching team', () => {
    UserStore.teams = fromJS([
      {id: 1, name: 'A'},
      {id: 2, name: 'B'},
    ])

    UserStore.removeTeam(fromJS({id: 1, name: 'A'}))

    expect(UserStore.getAllTeams()).toEqual([{id: 2, name: 'B'}])
  })

  test('removeTeam is a no-op when the team is not found', () => {
    UserStore.teams = fromJS([{id: 1, name: 'A'}])

    UserStore.removeTeam(fromJS({id: 99, name: 'Z'}))

    expect(UserStore.getAllTeams()).toEqual([{id: 1, name: 'A'}])
  })

  test('getSelectedTeam returns the selected team', () => {
    UserStore.selectedTeam = {id: 1}

    expect(UserStore.getSelectedTeam()).toEqual({id: 1})
  })

  test('getUserName returns the full name when logged in', () => {
    UserStore.userInfo = {user: {first_name: 'John', last_name: 'Doe'}}

    expect(UserStore.getUserName()).toBe('John Doe')
  })

  test('getUserName returns Anonymous when not logged in', () => {
    expect(UserStore.getUserName()).toBe('Anonymous')
  })

  test('getUserMetadata returns metadata when logged in', () => {
    UserStore.userInfo = {metadata: {locale: 'en-US'}}

    expect(UserStore.getUserMetadata()).toEqual({locale: 'en-US'})
  })

  test('getUserMetadata returns undefined when not logged in', () => {
    expect(UserStore.getUserMetadata()).toBeUndefined()
  })

  test('isUserLogged reflects whether userInfo is set', () => {
    expect(UserStore.isUserLogged()).toBe(false)

    UserStore.userInfo = {user: {}}

    expect(UserStore.isUserLogged()).toBe(true)
  })

  test('getDefaultConnectedService returns the service flagged as default', () => {
    UserStore.userInfo = {
      connected_services: [
        {id: 1, is_default: false},
        {id: 2, is_default: true},
      ],
    }

    expect(UserStore.getDefaultConnectedService()).toEqual({
      id: 2,
      is_default: true,
    })
  })

  test('getDefaultConnectedService falls back to the first selectable service', () => {
    UserStore.userInfo = {
      connected_services: [
        {id: 1, is_default: false},
        {id: 2, is_default: false, expired_at: '2020-01-01'},
      ],
    }

    expect(UserStore.getDefaultConnectedService()).toEqual({
      id: 1,
      is_default: false,
    })
  })

  test('updateConnectedService replaces the matching service', () => {
    UserStore.userInfo = {
      connected_services: [{id: 1, name: 'old'}, {id: 2}],
    }

    const result = UserStore.updateConnectedService({id: 1, name: 'new'})

    expect(result).toEqual([{id: 1, name: 'new'}, {id: 2}])
  })

  test('RENDER_TEAMS action updates teams and emits change', () => {
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: UserConstants.RENDER_TEAMS,
      teams: [{id: 1, name: 'A'}],
    })

    expect(emitSpy).toHaveBeenCalledWith(
      UserConstants.RENDER_TEAMS,
      UserStore.teams,
    )
  })

  test('ManageConstants.UPDATE_TEAM_NAME action emits updated team then teams', () => {
    UserStore.teams = fromJS([{id: 1, name: 'A'}])
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.UPDATE_TEAM_NAME,
      team: {id: 1, name: 'A2'},
    })

    expect(emitSpy.mock.calls[0][0]).toBe(UserConstants.UPDATE_TEAM)
    expect(emitSpy.mock.calls[0][1].toJS()).toEqual({id: 1, name: 'A2'})
    expect(emitSpy.mock.calls[1]).toEqual([
      UserConstants.UPDATE_TEAMS,
      UserStore.teams,
    ])
  })

  test('ManageConstants.UPDATE_TEAM_MEMBERS action emits updated team then teams', () => {
    UserStore.teams = fromJS([{id: 1, name: 'A'}])
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.UPDATE_TEAM_MEMBERS,
      team: fromJS({id: 1, name: 'A'}),
      members: [{id: 10}],
      pending_invitations: [{id: 20}],
    })

    expect(emitSpy.mock.calls[0][0]).toBe(UserConstants.UPDATE_TEAM)
    expect(emitSpy.mock.calls[0][1].toJS()).toEqual({
      id: 1,
      name: 'A',
      members: [{id: 10}],
      pending_invitations: [{id: 20}],
    })
  })

  test('UserConstants.UPDATE_TEAM action emits updated team then teams', () => {
    UserStore.teams = fromJS([{id: 1, name: 'A'}])
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: UserConstants.UPDATE_TEAM,
      team: {id: 1, name: 'A3'},
    })

    expect(emitSpy.mock.calls[0][1].toJS()).toEqual({id: 1, name: 'A3'})
  })

  test('UserConstants.UPDATE_TEAMS action replaces teams and emits change', () => {
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: UserConstants.UPDATE_TEAMS,
      teams: [{id: 5, name: 'E'}],
    })

    expect(UserStore.getAllTeams()).toEqual([{id: 5, name: 'E'}])
    expect(emitSpy).toHaveBeenCalledWith(
      UserConstants.UPDATE_TEAMS,
      UserStore.teams,
    )
  })

  test('CHOOSE_TEAM action emits the action type, team id and team', () => {
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: UserConstants.CHOOSE_TEAM,
      teamId: 1,
      team: {id: 1},
    })

    expect(emitSpy).toHaveBeenCalledWith(UserConstants.CHOOSE_TEAM, 1, {id: 1})
  })

  test('ManageConstants.REMOVE_TEAM action removes the team and emits render teams', () => {
    UserStore.teams = fromJS([{id: 1, name: 'A'}])
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.REMOVE_TEAM,
      team: fromJS({id: 1, name: 'A'}),
    })

    expect(UserStore.getAllTeams()).toEqual([])
    expect(emitSpy).toHaveBeenCalledWith(
      UserConstants.RENDER_TEAMS,
      UserStore.teams,
    )
  })

  test('UserConstants.ADD_TEAM action appends the team and emits render teams', () => {
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: UserConstants.ADD_TEAM,
      team: {id: 9, name: 'New'},
    })

    expect(UserStore.getAllTeams()).toEqual([{id: 9, name: 'New'}])
    expect(emitSpy).toHaveBeenCalledWith(
      UserConstants.RENDER_TEAMS,
      UserStore.teams,
    )
  })

  test('UserConstants.UPDATE_USER action stores the user and emits change', () => {
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: UserConstants.UPDATE_USER,
      user: {user: {first_name: 'John', last_name: 'Doe'}},
    })

    expect(emitSpy).toHaveBeenCalledWith(
      UserConstants.UPDATE_USER,
      UserStore.userInfo,
    )
  })

  test('ManageConstants.OPEN_CREATE_TEAM_MODAL action emits the action type', () => {
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({actionType: ManageConstants.OPEN_CREATE_TEAM_MODAL})

    expect(emitSpy).toHaveBeenCalledWith(ManageConstants.OPEN_CREATE_TEAM_MODAL)
  })

  test('ManageConstants.OPEN_MODIFY_TEAM_MODAL action emits team and hideChangeName flag', () => {
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.OPEN_MODIFY_TEAM_MODAL,
      team: {id: 1, name: 'A'},
      hideChangeName: true,
    })

    const [actionType, teamArg, hideChangeName] = emitSpy.mock.calls[0]
    expect(actionType).toBe(ManageConstants.OPEN_MODIFY_TEAM_MODAL)
    expect(teamArg.toJS()).toEqual({id: 1, name: 'A'})
    expect(hideChangeName).toBe(true)
  })

  test('ManageConstants.OPEN_INFO_TEAMS_POPUP action emits the action type', () => {
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({actionType: ManageConstants.OPEN_INFO_TEAMS_POPUP})

    expect(emitSpy).toHaveBeenCalledWith(ManageConstants.OPEN_INFO_TEAMS_POPUP)
  })

  test('ManageConstants.SELECTED_TEAM action sets the selected team without emitting', () => {
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.SELECTED_TEAM,
      selectedTeam: {id: 1},
    })

    expect(UserStore.getSelectedTeam()).toEqual({id: 1})
    expect(emitSpy).not.toHaveBeenCalled()
  })

  test('UserConstants.FORCE_RELOAD action emits the action type', () => {
    const emitSpy = jest.spyOn(UserStore, 'emitChange')

    AppDispatcher.dispatch({actionType: UserConstants.FORCE_RELOAD})

    expect(emitSpy).toHaveBeenCalledWith(UserConstants.FORCE_RELOAD)
  })
})
