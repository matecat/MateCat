import AppDispatcher from './AppDispatcher'
import ProjectsStore from './ProjectsStore'
import ManageConstants from '../constants/ManageConstants'
import {fromJS} from 'immutable'

const initialProjects = [
  {
    id: 1,
    name: 'Project One',
    id_assignee: null,
    id_team: 10,
    jobs: [
      {
        id: 100,
        password: 'pwd100',
        revise_passwords: [
          {revision_number: 1, password: 'r1'},
          {revision_number: 2, password: 'r2'},
        ],
        translator: null,
        outsource: null,
      },
      {
        id: 101,
        password: 'pwd101',
        revise_passwords: [],
        translator: null,
        outsource: null,
      },
    ],
  },
  {
    id: 2,
    name: 'Project Two',
    id_assignee: null,
    id_team: 20,
    jobs: [
      {
        id: 200,
        password: 'pwd200',
        revise_passwords: [],
        translator: null,
        outsource: null,
      },
    ],
  },
]

describe('ProjectsStore', () => {
  beforeEach(() => {
    ProjectsStore.projects = fromJS(initialProjects)
    jest.clearAllMocks()
  })

  test('setProjects stores the given projects as immutable data', () => {
    ProjectsStore.setProjects(initialProjects)

    expect(ProjectsStore.projects.toJS()).toEqual(initialProjects)
  })

  test('updateAll replaces the current projects', () => {
    ProjectsStore.updateAll([{id: 5, name: 'Only'}])

    expect(ProjectsStore.projects.toJS()).toEqual([{id: 5, name: 'Only'}])
  })

  test('addProjects appends projects to the existing list', () => {
    ProjectsStore.setProjects([{id: 1, name: 'A'}])
    ProjectsStore.addProjects([{id: 2, name: 'B'}])

    expect(ProjectsStore.projects.toJS()).toEqual([
      {id: 1, name: 'A'},
      {id: 2, name: 'B'},
    ])
  })

  test('removeProject removes the matching project', () => {
    ProjectsStore.removeProject(fromJS({id: 1}))

    expect(ProjectsStore.projects.toJS().map((p) => p.id)).toEqual([2])
  })

  test('removeJob removes only the matching job when other jobs remain', () => {
    const project = ProjectsStore.projects.get(0)
    ProjectsStore.removeJob(project, fromJS({id: 100}))

    const updatedProject = ProjectsStore.projects.toJS().find((p) => p.id === 1)
    expect(updatedProject.jobs.map((j) => j.id)).toEqual([101])
  })

  test('removeJob removes the whole project when it was the last job', () => {
    const project = ProjectsStore.projects.get(1)
    ProjectsStore.removeJob(project, fromJS({id: 200}))

    expect(ProjectsStore.projects.toJS().map((p) => p.id)).toEqual([1])
  })

  test('changeJobPass updates the main password when no revision number is given', () => {
    ProjectsStore.changeJobPass(1, 100, 'newpass', 'pwd100', undefined, {
      uid: 9,
    })

    const job = ProjectsStore.projects
      .toJS()
      .find((p) => p.id === 1)
      .jobs.find((j) => j.id === 100)
    expect(job.password).toBe('newpass')
    expect(job.translator).toEqual({uid: 9})
  })

  test('changeJobPass updates the first revision password', () => {
    ProjectsStore.changeJobPass(1, 100, 'revpass1', 'pwd100', 1, {uid: 9})

    const job = ProjectsStore.projects
      .toJS()
      .find((p) => p.id === 1)
      .jobs.find((j) => j.id === 100)
    expect(job.revise_passwords[0].password).toBe('revpass1')
  })

  test('changeJobPass updates the second revision password', () => {
    ProjectsStore.changeJobPass(1, 100, 'revpass2', 'pwd100', 2, {uid: 9})

    const job = ProjectsStore.projects
      .toJS()
      .find((p) => p.id === 1)
      .jobs.find((j) => j.id === 100)
    expect(job.revise_passwords[1].password).toBe('revpass2')
  })

  test('changeProjectName renames the matching project', () => {
    ProjectsStore.changeProjectName(fromJS({id: 1}), 'Renamed')

    expect(ProjectsStore.projects.toJS().find((p) => p.id === 1).name).toBe(
      'Renamed',
    )
  })

  test('changeProjectAssignee sets the assignee uid when a user is given', () => {
    ProjectsStore.changeProjectAssignee(fromJS({id: 1}), fromJS({uid: 42}))

    expect(
      ProjectsStore.projects.toJS().find((p) => p.id === 1).id_assignee,
    ).toBe(42)
  })

  test('changeProjectAssignee clears the assignee when no user is given', () => {
    ProjectsStore.changeProjectAssignee(fromJS({id: 1}), undefined)

    expect(
      ProjectsStore.projects.toJS().find((p) => p.id === 1).id_assignee,
    ).toBeUndefined()
  })

  test('changeProjectTeam sets the team id as an integer', () => {
    ProjectsStore.changeProjectTeam(fromJS({id: 1}), '30')

    expect(ProjectsStore.projects.toJS().find((p) => p.id === 1).id_team).toBe(
      30,
    )
  })

  test('updateJobOusource updates the matching job when the project is found', () => {
    const project = ProjectsStore.projects.get(0)
    const job = ProjectsStore.projects.getIn([0, 'jobs', 0])

    ProjectsStore.updateJobOusource(project, job, {outsourced: true})

    const updatedJob = ProjectsStore.projects
      .toJS()
      .find((p) => p.id === 1)
      .jobs.find((j) => j.id === 100)
    expect(updatedJob.outsource).toEqual({outsourced: true})
  })

  test('updateJobOusource is a no-op when the project is not found', () => {
    const project = fromJS({id: 999})
    const job = ProjectsStore.projects.getIn([0, 'jobs', 0])
    const before = ProjectsStore.projects

    ProjectsStore.updateJobOusource(project, job, {outsourced: true})

    expect(ProjectsStore.projects).toBe(before)
  })

  test('assignTranslator sets the translator for the matching job', () => {
    ProjectsStore.assignTranslator(1, 100, 'pwd100', {uid: 7})

    const job = ProjectsStore.projects
      .toJS()
      .find((p) => p.id === 1)
      .jobs.find((j) => j.id === 100)
    expect(job.translator).toEqual({uid: 7})
  })

  test('setSecondPassUrl sets the second revision password entry', () => {
    ProjectsStore.setSecondPassUrl(1, 100, 'pwd100', 'secondpass')

    const job = ProjectsStore.projects
      .toJS()
      .find((p) => p.id === 1)
      .jobs.find((j) => j.id === 100)
    expect(job.revise_passwords[1]).toEqual({
      revision_number: 2,
      password: 'secondpass',
    })
  })

  test('unwrapImmutableObject converts an immutable object to plain data', () => {
    expect(ProjectsStore.unwrapImmutableObject(fromJS({a: 1}))).toEqual({
      a: 1,
    })
  })

  test('unwrapImmutableObject returns plain objects unchanged', () => {
    const plain = {a: 1}

    expect(ProjectsStore.unwrapImmutableObject(plain)).toBe(plain)
  })

  test('RENDER_PROJECTS action stores projects and emits change', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.RENDER_PROJECTS,
      projects: initialProjects,
      team: {id: 10},
      teams: [{id: 10}],
      hideSpinner: true,
      filtering: false,
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.RENDER_PROJECTS,
      ProjectsStore.projects,
      fromJS({id: 10}),
      fromJS([{id: 10}]),
      true,
      false,
    )
  })

  test('RENDER_ALL_TEAM_PROJECTS action stores projects and emits change', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.RENDER_ALL_TEAM_PROJECTS,
      projects: initialProjects,
      teams: [{id: 10}],
      hideSpinner: false,
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.RENDER_ALL_TEAM_PROJECTS,
      ProjectsStore.projects,
      fromJS([{id: 10}]),
      false,
    )
  })

  test('UPDATE_PROJECTS action replaces projects and emits change', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.UPDATE_PROJECTS,
      projects: [{id: 5, name: 'Only'}],
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.UPDATE_PROJECTS,
      ProjectsStore.projects,
    )
  })

  test('RENDER_MORE_PROJECTS action appends projects and emits render event', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.RENDER_MORE_PROJECTS,
      project: [{id: 3, name: 'Three'}],
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.RENDER_PROJECTS,
      ProjectsStore.projects,
    )
  })

  test('OPEN_JOB_SETTINGS action emits the job and project name', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.OPEN_JOB_SETTINGS,
      job: {id: 100},
      prName: 'Project One',
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.OPEN_JOB_SETTINGS,
      {id: 100},
      'Project One',
    )
  })

  test('OPEN_JOB_TM_PANEL action emits the job and project name', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.OPEN_JOB_TM_PANEL,
      job: {id: 100},
      prName: 'Project One',
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.OPEN_JOB_TM_PANEL,
      {id: 100},
      'Project One',
    )
  })

  test('REMOVE_PROJECT action removes the project and emits render event', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.REMOVE_PROJECT,
      project: fromJS({id: 1}),
    })

    expect(ProjectsStore.projects.toJS().map((p) => p.id)).toEqual([2])
    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.RENDER_PROJECTS,
      ProjectsStore.projects,
    )
  })

  test('REMOVE_JOB action removes the job and emits render event', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')
    const project = ProjectsStore.projects.get(0)

    AppDispatcher.dispatch({
      actionType: ManageConstants.REMOVE_JOB,
      project,
      job: fromJS({id: 100}),
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.RENDER_PROJECTS,
      ProjectsStore.projects,
    )
  })

  test('CHANGE_JOB_PASS action updates the password and emits render event', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.CHANGE_JOB_PASS,
      projectId: 1,
      jobId: 100,
      password: 'newpass',
      oldPassword: 'pwd100',
      revision_number: undefined,
      oldTranslator: {uid: 1},
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.RENDER_PROJECTS,
      ProjectsStore.projects,
    )
  })

  test('NO_MORE_PROJECTS action emits the action type', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({actionType: ManageConstants.NO_MORE_PROJECTS})

    expect(emitSpy).toHaveBeenCalledWith(ManageConstants.NO_MORE_PROJECTS)
  })

  test('SHOW_RELOAD_SPINNER action emits the action type', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({actionType: ManageConstants.SHOW_RELOAD_SPINNER})

    expect(emitSpy).toHaveBeenCalledWith(ManageConstants.SHOW_RELOAD_SPINNER)
  })

  test('CHANGE_PROJECT_NAME action renames the project and emits update event', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.CHANGE_PROJECT_NAME,
      project: fromJS({id: 1}),
      newName: 'Renamed',
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.UPDATE_PROJECTS,
      ProjectsStore.projects,
    )
  })

  test('CHANGE_PROJECT_ASSIGNEE action updates assignee and emits both events', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')
    const project = fromJS({id: 1})
    const user = fromJS({uid: 42})

    AppDispatcher.dispatch({
      actionType: ManageConstants.CHANGE_PROJECT_ASSIGNEE,
      project,
      user,
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.CHANGE_PROJECT_ASSIGNEE,
      project,
      user,
    )
    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.UPDATE_PROJECTS,
      ProjectsStore.projects,
    )
  })

  test('CHANGE_PROJECT_TEAM action updates the team and emits update event', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.CHANGE_PROJECT_TEAM,
      project: fromJS({id: 1}),
      teamId: '50',
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.UPDATE_PROJECTS,
      ProjectsStore.projects,
    )
  })

  test('ADD_SECOND_PASS action sets the second pass password and emits update event', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.ADD_SECOND_PASS,
      idProject: 1,
      idJob: 100,
      passwordJob: 'pwd100',
      secondPassPassword: 'secondpass',
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.UPDATE_PROJECTS,
      ProjectsStore.projects,
    )
  })

  test('HIDE_PROJECT action emits the immutable project', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.HIDE_PROJECT,
      project: {id: 1},
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.HIDE_PROJECT,
      fromJS({id: 1}),
    )
  })

  test('ENABLE_DOWNLOAD_BUTTON action emits the project id', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.ENABLE_DOWNLOAD_BUTTON,
      idProject: 1,
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.ENABLE_DOWNLOAD_BUTTON,
      1,
    )
  })

  test('DISABLE_DOWNLOAD_BUTTON action emits the project id', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.DISABLE_DOWNLOAD_BUTTON,
      idProject: 1,
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.DISABLE_DOWNLOAD_BUTTON,
      1,
    )
  })

  test('ASSIGN_TRANSLATOR action assigns the translator and emits render event', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.ASSIGN_TRANSLATOR,
      projectId: 1,
      jobId: 100,
      jobPassword: 'pwd100',
      translator: {uid: 7},
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.RENDER_PROJECTS,
      ProjectsStore.projects,
    )
  })

  test('RELOAD_PROJECTS action emits the action type', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({actionType: ManageConstants.RELOAD_PROJECTS})

    expect(emitSpy).toHaveBeenCalledWith(ManageConstants.RELOAD_PROJECTS)
  })

  test('FILTER_PROJECTS action emits the filter criteria', () => {
    const emitSpy = jest.spyOn(ProjectsStore, 'emitChange')

    AppDispatcher.dispatch({
      actionType: ManageConstants.FILTER_PROJECTS,
      memberUid: 5,
      name: 'search',
      status: 'active',
    })

    expect(emitSpy).toHaveBeenCalledWith(
      ManageConstants.FILTER_PROJECTS,
      5,
      'search',
      'active',
    )
  })
})
