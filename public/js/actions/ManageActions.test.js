jest.mock('../stores/AppDispatcher', () => ({
  dispatch: jest.fn(),
}))
jest.mock('../constants/ManageConstants', () => ({
  RENDER_PROJECTS: 'RENDER_PROJECTS',
  UPDATE_PROJECTS: 'UPDATE_PROJECTS',
  RELOAD_PROJECTS: 'RELOAD_PROJECTS',
  RENDER_MORE_PROJECTS: 'RENDER_MORE_PROJECTS',
  OPEN_JOB_SETTINGS: 'OPEN_JOB_SETTINGS',
  REMOVE_PROJECT: 'REMOVE_PROJECT',
  REMOVE_JOB: 'REMOVE_JOB',
  CHANGE_JOB_PASS: 'CHANGE_JOB_PASS',
  OPEN_JOB_TM_PANEL: 'OPEN_JOB_TM_PANEL',
  NO_MORE_PROJECTS: 'NO_MORE_PROJECTS',
  CHANGE_PROJECT_NAME: 'CHANGE_PROJECT_NAME',
  HIDE_PROJECT: 'HIDE_PROJECT',
  ASSIGN_TRANSLATOR: 'ASSIGN_TRANSLATOR',
  SHOW_RELOAD_SPINNER: 'SHOW_RELOAD_SPINNER',
  ADD_SECOND_PASS: 'ADD_SECOND_PASS',
  OPEN_CREATE_TEAM_MODAL: 'OPEN_CREATE_TEAM_MODAL',
  OPEN_MODIFY_TEAM_MODAL: 'OPEN_MODIFY_TEAM_MODAL',
  OPEN_INFO_TEAMS_POPUP: 'OPEN_INFO_TEAMS_POPUP',
  REMOVE_TEAM: 'REMOVE_TEAM',
  UPDATE_TEAM_NAME: 'UPDATE_TEAM_NAME',
  UPDATE_TEAM_MEMBERS: 'UPDATE_TEAM_MEMBERS',
  FILTER_PROJECTS: 'FILTER_PROJECTS',
  CHANGE_PROJECT_ASSIGNEE: 'CHANGE_PROJECT_ASSIGNEE',
  CHANGE_PROJECT_TEAM: 'CHANGE_PROJECT_TEAM',
  SELECTED_TEAM: 'SELECTED_TEAM',
  ALL_MEMBERS_FILTER: 'ALL_MEMBERS_FILTER',
  NOT_ASSIGNED_FILTER: 'NOT_ASSIGNED_FILTER',
  ENABLE_DOWNLOAD_BUTTON: 'ENABLE_DOWNLOAD_BUTTON',
  DISABLE_DOWNLOAD_BUTTON: 'DISABLE_DOWNLOAD_BUTTON',
  UPDATE_JOB_OUTSOURCE: 'UPDATE_JOB_OUTSOURCE',
}))
jest.mock('../constants/UserConstants', () => ({
  RENDER_TEAMS: 'RENDER_TEAMS',
  ADD_TEAM: 'ADD_TEAM',
  UPDATE_TEAM: 'UPDATE_TEAM',
  UPDATE_TEAMS: 'UPDATE_TEAMS',
  CHOOSE_TEAM: 'CHOOSE_TEAM',
  UPDATE_USER: 'UPDATE_USER',
  UPDATE_USER_NAME: 'UPDATE_USER_NAME',
  FORCE_RELOAD: 'FORCE_RELOAD',
}))
jest.mock('../stores/UserStore', () => ({
  teams: undefined,
  getSelectedTeam: jest.fn(),
  getUser: jest.fn(),
}))
jest.mock('../api/changeProjectName', () => ({
  changeProjectName: jest.fn(),
}))
jest.mock('../api/changeProjectAssignee', () => ({
  changeProjectAssignee: jest.fn(),
}))
jest.mock('../api/changeProjectTeam', () => ({
  changeProjectTeam: jest.fn(),
}))
jest.mock('../api/getSecondPassReview', () => ({
  getSecondPassReview: jest.fn(),
}))
jest.mock('../api/getUserData', () => ({
  getUserData: jest.fn(),
}))
jest.mock('../api/getTeamMembers', () => ({
  getTeamMembers: jest.fn(),
}))
jest.mock('../api/createTeam', () => ({
  createTeam: jest.fn(),
}))
jest.mock('../api/addUserTeam', () => ({
  addUserTeam: jest.fn(),
}))
jest.mock('../api/removeTeamUser', () => ({
  removeTeamUser: jest.fn(),
}))
jest.mock('../api/updateTeamName', () => ({
  updateTeamName: jest.fn(),
}))
jest.mock('../api/changeProjectStatus', () => ({
  changeProjectStatus: jest.fn(),
}))
jest.mock('../api/changeJobStatus', () => ({
  changeJobStatus: jest.fn(),
}))
jest.mock('./CatToolActions', () => ({
  addNotification: jest.fn(),
}))
jest.mock('./UserActions', () => ({
  setTeamInStorage: jest.fn(),
}))

import {fromJS} from 'immutable'
import ManageActions from './ManageActions'
import AppDispatcher from '../stores/AppDispatcher'
import UserStore from '../stores/UserStore'
import CatToolActions from './CatToolActions'
import UserActions from './UserActions'
import {changeProjectName} from '../api/changeProjectName'
import {changeProjectAssignee} from '../api/changeProjectAssignee'
import {changeProjectTeam} from '../api/changeProjectTeam'
import {getSecondPassReview} from '../api/getSecondPassReview'
import {getUserData} from '../api/getUserData'
import {getTeamMembers} from '../api/getTeamMembers'
import {createTeam} from '../api/createTeam'
import {addUserTeam} from '../api/addUserTeam'
import {removeTeamUser} from '../api/removeTeamUser'
import {updateTeamName} from '../api/updateTeamName'
import {changeProjectStatus} from '../api/changeProjectStatus'
import {changeJobStatus} from '../api/changeJobStatus'

const flushPromises = async () => {
  await Promise.resolve()
  await Promise.resolve()
  await Promise.resolve()
}

describe('ManageActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    global.config = {...global.config, userMail: 'user@test.com'}
    document.body.classList.remove('manage')
    localStorage.clear()
  })

  test('renderProjects dispatches RENDER_PROJECTS and stores popup key', () => {
    ManageActions.renderProjects(['p1'], 'team', ['t1'], true, true)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RENDER_PROJECTS',
      projects: ['p1'],
      team: 'team',
      teams: ['t1'],
      hideSpinner: true,
      filtering: true,
    })
    expect(ManageActions.popupInfoTeamsStorageName).toBe(
      'infoTeamPopup-user@test.com',
    )
  })

  test('updateProjects dispatches UPDATE_PROJECTS', () => {
    ManageActions.updateProjects(['p1'])

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_PROJECTS',
      projects: ['p1'],
    })
  })

  test('renderMoreProjects dispatches RENDER_MORE_PROJECTS', () => {
    ManageActions.renderMoreProjects(['p2'])

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RENDER_MORE_PROJECTS',
      project: ['p2'],
    })
  })

  test('openJobSettings dispatches OPEN_JOB_SETTINGS', () => {
    ManageActions.openJobSettings({id: 1}, 'p')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'OPEN_JOB_SETTINGS',
      job: {id: 1},
      prName: 'p',
    })
  })

  test('openJobTMPanel dispatches OPEN_JOB_TM_PANEL', () => {
    ManageActions.openJobTMPanel({id: 1}, 'p')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'OPEN_JOB_TM_PANEL',
      job: {id: 1},
      prName: 'p',
    })
  })

  test('updateStatusProject dispatches HIDE_PROJECT and removes project after timeout', async () => {
    jest.useFakeTimers()
    changeProjectStatus.mockResolvedValueOnce({})
    const removeSpy = jest.spyOn(ManageActions, 'removeProject')
    const project = fromJS({id: 1, password: 'pwd'})

    ManageActions.updateStatusProject(project, 'ACTIVE')
    await Promise.resolve()
    await Promise.resolve()

    expect(changeProjectStatus).toHaveBeenCalledWith(1, 'pwd', 'ACTIVE')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'HIDE_PROJECT',
      project,
    })

    jest.advanceTimersByTime(1000)
    expect(removeSpy).toHaveBeenCalledWith(project)

    jest.useRealTimers()
  })

  test('changeJobStatus hides project and removes job when it is the last job', async () => {
    jest.useFakeTimers()
    changeJobStatus.mockResolvedValueOnce({})
    const project = fromJS({id: 1, jobs: [1]})
    const job = fromJS({id: 2, password: 'pw'})

    ManageActions.changeJobStatus(project, job, 'DONE')
    await Promise.resolve()

    expect(changeJobStatus).toHaveBeenCalledWith(2, 'pw', 'DONE')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'HIDE_PROJECT',
      project,
    })

    jest.advanceTimersByTime(1000)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'REMOVE_JOB',
      project,
      job,
    })

    jest.useRealTimers()
  })

  test('changeJobStatus only removes job when other jobs remain', async () => {
    changeJobStatus.mockResolvedValueOnce({})
    const project = fromJS({id: 1, jobs: [1, 2]})
    const job = fromJS({id: 2, password: 'pw'})

    ManageActions.changeJobStatus(project, job, 'DONE')
    await Promise.resolve()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'REMOVE_JOB',
      project,
      job,
    })
    expect(AppDispatcher.dispatch).not.toHaveBeenCalledWith(
      expect.objectContaining({actionType: 'HIDE_PROJECT'}),
    )
  })

  test('changeJobPassword dispatches CHANGE_JOB_PASS', () => {
    const project = fromJS({id: 1})
    const job = fromJS({id: 2})

    ManageActions.changeJobPassword(project, job, 'new', 'old', 3, 'translator')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CHANGE_JOB_PASS',
      projectId: 1,
      jobId: 2,
      password: 'new',
      oldPassword: 'old',
      revision_number: 3,
      oldTranslator: 'translator',
    })
  })

  test('changeJobPasswordFromOutsource dispatches CHANGE_JOB_PASS', () => {
    ManageActions.changeJobPasswordFromOutsource({id: 1}, {id: 2}, 'new', 'old')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CHANGE_JOB_PASS',
      projectId: 1,
      jobId: 2,
      password: 'new',
      oldPassword: 'old',
    })
  })

  test('noMoreProjects dispatches NO_MORE_PROJECTS', () => {
    ManageActions.noMoreProjects()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'NO_MORE_PROJECTS',
    })
  })

  test('showReloadSpinner dispatches SHOW_RELOAD_SPINNER', () => {
    ManageActions.showReloadSpinner()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SHOW_RELOAD_SPINNER',
    })
  })

  test('filterProjects shows spinner then dispatches FILTER_PROJECTS with member uid', () => {
    const member = fromJS({user: {uid: 7}})

    ManageActions.filterProjects(member, 'name', 'active')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SHOW_RELOAD_SPINNER',
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'FILTER_PROJECTS',
      memberUid: 7,
      name: 'name',
      status: 'active',
    })
  })

  test('filterProjects passes member through directly when not Immutable', () => {
    ManageActions.filterProjects('memberUid', 'name', 'active')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'FILTER_PROJECTS',
      memberUid: 'memberUid',
      name: 'name',
      status: 'active',
    })
  })

  test('removeProject dispatches REMOVE_PROJECT', () => {
    ManageActions.removeProject({id: 1})

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'REMOVE_PROJECT',
      project: {id: 1},
    })
  })

  test('showNotificationProjectsChanged adds a warning notification', () => {
    ManageActions.showNotificationProjectsChanged()

    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Ooops...', type: 'warning'}),
    )
  })

  test('changeProjectAssignee updates assignee, team members and notifies on success', async () => {
    changeProjectAssignee.mockResolvedValueOnce({})
    getTeamMembers.mockResolvedValueOnce({
      members: ['m1'],
      pending_invitations: [],
    })
    const team = fromJS({id: 1})
    const project = fromJS({id: 2, name: 'Proj'})
    const user = fromJS({uid: 3, first_name: 'John', last_name: 'Doe'})

    ManageActions.changeProjectAssignee(team, project, user)
    await flushPromises()

    expect(changeProjectAssignee).toHaveBeenCalledWith(1, 2, 3)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CHANGE_PROJECT_ASSIGNEE',
      project,
      user,
    })
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Assignee changed'}),
    )
  })

  test('changeProjectAssignee handles null user as "Not assigned"', async () => {
    changeProjectAssignee.mockResolvedValueOnce({})
    getTeamMembers.mockResolvedValueOnce({
      members: [],
      pending_invitations: [],
    })
    const team = fromJS({id: 1})
    const project = fromJS({id: 2, name: 'Proj'})

    ManageActions.changeProjectAssignee(team, project, null)
    await flushPromises()

    expect(changeProjectAssignee).toHaveBeenCalledWith(1, 2, null)
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({text: expect.stringContaining('Not assigned')}),
    )
  })

  test('changeProjectAssignee reloads projects on failure', async () => {
    changeProjectAssignee.mockRejectedValueOnce(new Error('fail'))
    const team = fromJS({id: 1})
    const project = fromJS({id: 2, name: 'Proj'})

    ManageActions.changeProjectAssignee(team, project, null)
    await flushPromises()

    expect(CatToolActions.addNotification).toHaveBeenCalled()
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RELOAD_PROJECTS',
    })
  })

  test('changeProjectAssigneeBulk assigns projects and notifies on full success', async () => {
    getTeamMembers.mockResolvedValueOnce({
      members: [],
      pending_invitations: [],
    })
    changeProjectAssignee.mockResolvedValue({project: {id: 10}})
    const teams = [
      {
        id: 1,
        members: [{id: 5, user: {uid: 5, first_name: 'A', last_name: 'B'}}],
      },
    ]
    const projects = [{id: 10, id_team: 1}]

    ManageActions.changeProjectAssigneeBulk(5, projects, teams)
    await flushPromises()
    await flushPromises()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({actionType: 'UPDATE_TEAM'}),
    )
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({actionType: 'CHANGE_PROJECT_ASSIGNEE'}),
    )
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Assignee changed'}),
    )
  })

  test('changeProjectAssigneeBulk notifies partial failure', async () => {
    getTeamMembers.mockResolvedValueOnce({
      members: [],
      pending_invitations: [],
    })
    changeProjectAssignee
      .mockResolvedValueOnce({project: {id: 10}})
      .mockRejectedValueOnce(new Error('fail'))
    const teams = [
      {
        id: 1,
        members: [{id: 5, user: {uid: 5, first_name: 'A', last_name: 'B'}}],
      },
    ]
    const projects = [
      {id: 10, id_team: 1},
      {id: 11, id_team: 1},
    ]

    ManageActions.changeProjectAssigneeBulk(5, projects, teams)
    await flushPromises()
    await flushPromises()

    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Assignee changed'}),
    )
  })

  test('changeProjectName dispatches CHANGE_PROJECT_NAME on success', async () => {
    changeProjectName.mockResolvedValueOnce({name: 'New Name'})
    const project = fromJS({id: 1, password: 'pwd'})

    ManageActions.changeProjectName(project, 'New Name')
    await flushPromises()

    expect(changeProjectName).toHaveBeenCalledWith({
      idProject: 1,
      passwordProject: 'pwd',
      newName: 'New Name',
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CHANGE_PROJECT_NAME',
      project,
      newName: 'New Name',
    })
  })

  test('changeProjectTeam moves project from personal team to a shared team', async () => {
    changeProjectTeam.mockResolvedValueOnce({})
    const teamsList = fromJS([{id: 2, type: 'team', name: 'Shared'}])
    UserStore.teams = teamsList
    UserStore.getSelectedTeam.mockReturnValue({type: 'personal', id: 1})
    getTeamMembers.mockResolvedValueOnce({
      members: [],
      pending_invitations: [],
    })
    const project = fromJS({id: 5, name: 'Proj'})

    jest.useFakeTimers()
    ManageActions.changeProjectTeam(2, project)
    await flushPromises()
    jest.runAllTimers()
    jest.useRealTimers()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith(
      expect.objectContaining({actionType: 'UPDATE_TEAM'}),
    )
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CHANGE_PROJECT_TEAM',
      project,
      teamId: 2,
    })
  })

  test('changeProjectTeam moves project between two non-personal teams', async () => {
    changeProjectTeam.mockResolvedValueOnce({})
    const teamsList = fromJS([{id: 2, type: 'team', name: 'Other'}])
    UserStore.teams = teamsList
    UserStore.getSelectedTeam.mockReturnValue({
      type: 'team',
      id: 1,
      name: 'Current',
    })
    getTeamMembers.mockResolvedValueOnce({
      members: [],
      pending_invitations: [],
    })
    const project = fromJS({id: 5, name: 'Proj'})

    jest.useFakeTimers()
    ManageActions.changeProjectTeam(2, project)
    await flushPromises()
    jest.advanceTimersByTime(1000)
    await flushPromises()
    jest.runAllTimers()
    jest.useRealTimers()

    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Project Moved'}),
    )
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'HIDE_PROJECT',
      project,
    })
  })

  test('changeProjectTeam reloads projects on failure', async () => {
    changeProjectTeam.mockRejectedValueOnce(new Error('fail'))
    const teamsList = fromJS([{id: 2, type: 'team', name: 'Other'}])
    UserStore.teams = teamsList
    const project = fromJS({id: 5, name: 'Proj'})

    ManageActions.changeProjectTeam(2, project)
    await flushPromises()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RELOAD_PROJECTS',
    })
  })

  test('changeProjectsTeamBulk moves multiple projects and notifies success', async () => {
    changeProjectTeam.mockResolvedValue({project: {id: 20}})
    const teamsList = fromJS([{id: 2, type: 'team', name: 'Shared'}])
    UserStore.teams = teamsList
    UserStore.getSelectedTeam.mockReturnValue({type: 'personal', id: 1})
    getTeamMembers.mockResolvedValueOnce({
      members: [],
      pending_invitations: [],
    })
    const projects = [{id: 20}]

    jest.useFakeTimers()
    ManageActions.changeProjectsTeamBulk(2, projects)
    await flushPromises()
    await flushPromises()
    jest.runAllTimers()
    jest.useRealTimers()

    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Projects moved'}),
    )
  })

  test('changeProjectsTeamBulk notifies partial failure', async () => {
    changeProjectTeam
      .mockResolvedValueOnce({project: {id: 20}})
      .mockRejectedValueOnce(new Error('fail'))
    const teamsList = fromJS([{id: 2, type: 'team', name: 'Shared'}])
    UserStore.teams = teamsList
    UserStore.getSelectedTeam.mockReturnValue({type: 'personal', id: 1})
    getTeamMembers.mockResolvedValueOnce({
      members: [],
      pending_invitations: [],
    })
    const projects = [{id: 20}, {id: 21}]

    jest.useFakeTimers()
    ManageActions.changeProjectsTeamBulk(2, projects)
    await flushPromises()
    await flushPromises()
    jest.runAllTimers()
    jest.useRealTimers()

    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Projects moved'}),
    )
  })

  test('assignTranslator dispatches ASSIGN_TRANSLATOR only when in manage view', () => {
    document.body.classList.add('manage')

    ManageActions.assignTranslator(1, 2, 'pwd', 'translator')

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ASSIGN_TRANSLATOR',
      projectId: 1,
      jobId: 2,
      jobPassword: 'pwd',
      translator: 'translator',
    })
  })

  test('assignTranslator does nothing outside manage view', () => {
    ManageActions.assignTranslator(1, 2, 'pwd', 'translator')

    expect(AppDispatcher.dispatch).not.toHaveBeenCalled()
  })

  test('enableDownloadButton dispatches ENABLE_DOWNLOAD_BUTTON', () => {
    ManageActions.enableDownloadButton(1)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ENABLE_DOWNLOAD_BUTTON',
      idProject: 1,
    })
  })

  test('disableDownloadButton dispatches DISABLE_DOWNLOAD_BUTTON', () => {
    ManageActions.disableDownloadButton(1)

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'DISABLE_DOWNLOAD_BUTTON',
      idProject: 1,
    })
  })

  test('checkPopupInfoTeams opens popup when nothing stored', () => {
    ManageActions.popupInfoTeamsStorageName = 'infoTeamPopup-user@test.com'

    ManageActions.checkPopupInfoTeams()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'OPEN_INFO_TEAMS_POPUP',
    })
  })

  test('checkPopupInfoTeams does not open popup when already stored', () => {
    ManageActions.popupInfoTeamsStorageName = 'infoTeamPopup-user@test.com'
    localStorage.setItem('infoTeamPopup-user@test.com', 'true')

    ManageActions.checkPopupInfoTeams()

    expect(AppDispatcher.dispatch).not.toHaveBeenCalled()
  })

  test('setPopupTeamsCookie stores popup flag', () => {
    ManageActions.popupInfoTeamsStorageName = 'infoTeamPopup-user@test.com'

    ManageActions.setPopupTeamsCookie()

    expect(localStorage.getItem('infoTeamPopup-user@test.com')).toBe('true')
  })

  test('getSecondPassReview dispatches ADD_SECOND_PASS on success', async () => {
    getSecondPassReview.mockResolvedValueOnce({
      chunk_review: {review_password: 'secret'},
    })

    await ManageActions.getSecondPassReview(1, 'pp', 2, 'pj')

    expect(getSecondPassReview).toHaveBeenCalledWith(1, 'pp', 2, 'pj')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ADD_SECOND_PASS',
      idProject: 1,
      passwordProject: 'pp',
      idJob: 2,
      passwordJob: 'pj',
      secondPassPassword: 'secret',
    })
  })

  test('openModifyTeamModal fetches members and dispatches modal action', async () => {
    getTeamMembers.mockResolvedValueOnce({
      members: ['m1'],
      pending_invitations: [],
    })
    const team = {id: 1}

    ManageActions.openModifyTeamModal(team)
    await flushPromises()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'OPEN_MODIFY_TEAM_MODAL',
      team: {id: 1, members: ['m1'], pending_invitations: []},
      hideChangeName: false,
    })
  })

  test('openAddTeamMemberModal fetches members and dispatches modal action', async () => {
    getTeamMembers.mockResolvedValueOnce({
      members: ['m1'],
      pending_invitations: [],
    })
    const team = {id: 1}

    ManageActions.openAddTeamMemberModal(team)
    await flushPromises()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'OPEN_MODIFY_TEAM_MODAL',
      team: {id: 1, members: ['m1'], pending_invitations: []},
      hideChangeName: true,
    })
  })

  test('openPopupTeams dispatches OPEN_INFO_TEAMS_POPUP', () => {
    ManageActions.openPopupTeams()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'OPEN_INFO_TEAMS_POPUP',
    })
  })

  test('createTeam creates a team and selects it', async () => {
    createTeam.mockResolvedValueOnce({team: {id: 99}})

    ManageActions.createTeam('New Team', ['m1'])
    await flushPromises()

    expect(createTeam).toHaveBeenCalledWith('New Team', ['m1'])
    expect(UserActions.setTeamInStorage).toHaveBeenCalledWith(99)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'ADD_TEAM',
      team: {id: 99},
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CHOOSE_TEAM',
      teamId: 99,
    })
  })

  test('changeTeam stores selection and updates team members', async () => {
    getTeamMembers.mockResolvedValueOnce({
      members: ['m1'],
      pending_invitations: [],
    })
    const team = {id: 7}

    ManageActions.changeTeam(team)
    await flushPromises()

    expect(UserActions.setTeamInStorage).toHaveBeenCalledWith(7)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_TEAM',
      team: {id: 7, members: ['m1'], pending_invitations: []},
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'CHOOSE_TEAM',
      teamId: 7,
    })
  })

  test('addUserToTeam adds a user and dispatches UPDATE_TEAM_MEMBERS', async () => {
    addUserTeam.mockResolvedValueOnce({
      members: ['m1'],
      pending_invitations: [],
    })
    const team = fromJS({id: 1})

    ManageActions.addUserToTeam(team, 'user@test.com')
    await flushPromises()

    expect(addUserTeam).toHaveBeenCalledWith({id: 1}, 'user@test.com')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_TEAM_MEMBERS',
      team,
      members: ['m1'],
      pending_invitations: [],
    })
  })

  test('removeUserFromTeam removes another member and dispatches updates', async () => {
    removeTeamUser.mockResolvedValueOnce({
      members: ['m1'],
      pending_invitations: [],
    })
    UserStore.getUser.mockReturnValue({user: {uid: 1}})
    const team = fromJS({id: 1})
    const user = fromJS({uid: 2})

    ManageActions.removeUserFromTeam(team, user)
    await flushPromises()

    expect(removeTeamUser).toHaveBeenCalledWith({id: 1}, 2)
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_TEAM_MEMBERS',
      team,
      members: ['m1'],
      pending_invitations: [],
    })
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RELOAD_PROJECTS',
    })
  })

  test('removeUserFromTeam removes self from a different team than the selected one', async () => {
    removeTeamUser.mockResolvedValueOnce({
      members: [],
      pending_invitations: [],
    })
    UserStore.getUser.mockReturnValue({user: {uid: 5}})
    UserStore.getSelectedTeam.mockReturnValue({id: 999})
    const team = fromJS({id: 1})
    const user = fromJS({uid: 5})

    ManageActions.removeUserFromTeam(team, user)
    await flushPromises()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'REMOVE_TEAM',
      team,
    })
  })

  test('removeUserFromTeam removes self from the selected team and reloads teams', async () => {
    removeTeamUser.mockResolvedValueOnce({
      members: [],
      pending_invitations: [],
    })
    UserStore.getUser.mockReturnValue({user: {uid: 5}})
    UserStore.getSelectedTeam.mockReturnValue({id: 1})
    getUserData.mockResolvedValueOnce({teams: [{id: 3}]})
    getTeamMembers.mockResolvedValueOnce({
      members: [],
      pending_invitations: [],
    })
    const team = fromJS({id: 1})
    const user = fromJS({uid: 5})

    ManageActions.removeUserFromTeam(team, user)
    await flushPromises()
    await flushPromises()

    const renderTeamsCall = AppDispatcher.dispatch.mock.calls.find(
      ([arg]) => arg.actionType === 'RENDER_TEAMS',
    )
    expect(renderTeamsCall[0].teams[0].id).toBe(3)
  })

  test('changeTeamName dispatches UPDATE_TEAM_NAME', async () => {
    updateTeamName.mockResolvedValueOnce({team: [{id: 1, name: 'New'}]})
    const team = {id: 1, name: 'Old'}

    ManageActions.changeTeamName(team, 'New')
    await flushPromises()

    expect(updateTeamName).toHaveBeenCalledWith(team, 'New')
    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'UPDATE_TEAM_NAME',
      oldTeam: team,
      team: {id: 1, name: 'New'},
    })
  })

  test('storeSelectedTeam dispatches SELECTED_TEAM', () => {
    ManageActions.storeSelectedTeam({id: 1})

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'SELECTED_TEAM',
      selectedTeam: {id: 1},
    })
  })

  test('reloadProjects dispatches RELOAD_PROJECTS', () => {
    ManageActions.reloadProjects()

    expect(AppDispatcher.dispatch).toHaveBeenCalledWith({
      actionType: 'RELOAD_PROJECTS',
    })
  })
})
