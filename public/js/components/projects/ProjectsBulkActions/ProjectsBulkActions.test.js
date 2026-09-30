import React, {useContext} from 'react'
import {render, screen, fireEvent, waitFor, act} from '@testing-library/react'
import {ProjectsBulkActions} from './ProjectsBulkActions'
import {ProjectsBulkActionsContext} from './ProjectsBulkActionsContext'
import ModalsActions from '../../../actions/ModalsActions'
import CatToolActions from '../../../actions/CatToolActions'
import ManageActions from '../../../actions/ManageActions'
import ProjectsStore from '../../../stores/ProjectsStore'
import ManageConstants from '../../../constants/ManageConstants'
import UserStore from '../../../stores/UserStore'
import UserConstants from '../../../constants/UserConstants'
import {changeJobPassword} from '../../../api/changeJobPassword'
import {BulkChangePassword} from './BulkChangePassword'
import BulkMoveToTeam from './BulkMoveToTeam'
import {BulkAssignToMember} from './BulkAssignToMember'
import ConfirmMessageModal from '../../modals/ConfirmMessageModal'

jest.mock('../../../actions/ModalsActions', () => ({
  showModalComponent: jest.fn(),
  onCloseModal: jest.fn(),
}))
jest.mock('../../../actions/CatToolActions', () => ({
  addNotification: jest.fn(),
}))
jest.mock('../../../actions/ManageActions', () => ({
  changeJobStatus: jest.fn(),
  getSecondPassReview: jest.fn(),
  changeJobPassword: jest.fn(),
  changeProjectsTeamBulk: jest.fn(),
  changeProjectAssigneeBulk: jest.fn(),
}))
jest.mock('../../../stores/ProjectsStore', () => ({
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))
jest.mock('../../../stores/UserStore', () => ({
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))
jest.mock('../../../api/changeJobPassword', () => ({
  changeJobPassword: jest.fn(),
}))

const job1 = {id: 1, password: 'pass1', status: 'active'}
const job2 = {id: 2, password: 'pass2', status: 'active'}
const job3 = {id: 3, password: 'pass3', status: 'active'}
const project1 = {id: 100, id_team: 1, jobs: [job1, job2]}
const project2 = {id: 101, id_team: 2, jobs: [job3]}
const teams = [
  {id: 1, type: 'standard'},
  {id: 2, type: 'personal'},
]

const TestConsumer = () => {
  const {jobsBulk, onCheckedJob, onCheckedProject} = useContext(
    ProjectsBulkActionsContext,
  )
  return (
    <div>
      <span data-testid="jobs-bulk">{jobsBulk.join(',')}</span>
      <button onClick={() => onCheckedJob(1)}>check-job-1</button>
      <button onClick={() => onCheckedJob(2)}>check-job-2</button>
      <button onClick={() => onCheckedJob(3)}>check-job-3</button>
      <button onClick={() => onCheckedProject(100)}>check-project-100</button>
      <button onClick={() => onCheckedProject(101)}>check-project-101</button>
    </div>
  )
}

const renderComponent = (props = {}) =>
  render(
    <ProjectsBulkActions
      projects={[project1, project2]}
      teams={teams}
      isSelectedTeamPersonal={false}
      {...props}
    >
      <TestConsumer />
    </ProjectsBulkActions>,
  )

const getListenerCallback = (store, constantKey) => {
  const call = store.addListener.mock.calls.find(([key]) => key === constantKey)
  return call[1]
}

afterEach(() => {
  jest.clearAllMocks()
})

describe('ProjectsBulkActions selection', () => {
  test('starts with nothing selected and the background hidden', () => {
    const {container} = renderComponent()
    expect(screen.getByTestId('jobs-bulk')).toHaveTextContent('')
    expect(
      container.querySelector('.project-bulk-actions-background'),
    ).toHaveClass('project-bulk-actions-background-hidden')
  })

  test('checking a single job adds it to the selection and unhides the bar', () => {
    const {container} = renderComponent()
    fireEvent.click(screen.getByText('check-job-1'))
    expect(screen.getByTestId('jobs-bulk')).toHaveTextContent('1')
    expect(
      container.querySelector('.project-bulk-actions-background'),
    ).not.toHaveClass('project-bulk-actions-background-hidden')
  })

  test('checking the same job twice toggles it off', () => {
    renderComponent()
    fireEvent.click(screen.getByText('check-job-1'))
    fireEvent.click(screen.getByText('check-job-1'))
    expect(screen.getByTestId('jobs-bulk')).toHaveTextContent('')
  })

  test('shift-clicking a range selects every job between anchor and target', () => {
    renderComponent()
    fireEvent.click(screen.getByText('check-job-1'))
    fireEvent.keyDown(document, {key: 'Shift'})
    fireEvent.click(screen.getByText('check-job-3'))
    fireEvent.keyUp(document, {key: 'Shift'})
    expect(screen.getByTestId('jobs-bulk')).toHaveTextContent('1,2,3')
  })

  test('checking a project selects every job belonging to it', () => {
    renderComponent()
    fireEvent.click(screen.getByText('check-project-100'))
    expect(screen.getByTestId('jobs-bulk')).toHaveTextContent('1,2')
  })

  test('checking an already-fully-selected project clears its jobs', () => {
    renderComponent()
    fireEvent.click(screen.getByText('check-project-100'))
    fireEvent.click(screen.getByText('check-project-100'))
    expect(screen.getByTestId('jobs-bulk')).toHaveTextContent('')
  })

  test('the select-all button selects every visible job', () => {
    renderComponent()
    fireEvent.click(screen.getByLabelText('Select all visible jobs'))
    expect(screen.getByTestId('jobs-bulk')).toHaveTextContent('1,2,3')
  })

  test('the clear-selection button empties the selection', () => {
    renderComponent()
    fireEvent.click(screen.getByLabelText('Select all visible jobs'))
    fireEvent.click(screen.getByLabelText('Clear selection'))
    expect(screen.getByTestId('jobs-bulk')).toHaveTextContent('')
  })

  test('shows the reached-limit styling once 100 jobs are selected', () => {
    const manyJobs = Array.from({length: 100}, (_, i) => ({
      id: i + 1,
      password: `pass${i + 1}`,
      status: 'active',
    }))
    const bigProject = {id: 200, id_team: 1, jobs: manyJobs}
    const {container} = render(
      <ProjectsBulkActions
        projects={[bigProject]}
        teams={teams}
        isSelectedTeamPersonal={false}
      >
        <TestConsumer />
      </ProjectsBulkActions>,
    )
    fireEvent.click(screen.getByLabelText('Select all visible jobs'))
    expect(
      screen.getByLabelText('Maximum number of selected jobs reached'),
    ).toBeInTheDocument()
    expect(container.querySelector('.jobs-selected')).toHaveClass(
      'jobs-selected-reached-limit',
    )
  })
})

describe('ProjectsBulkActions store-driven filter changes', () => {
  test('switches to the archived action set and clears selection on FILTER_PROJECTS', () => {
    renderComponent()
    fireEvent.click(screen.getByText('check-job-1'))
    expect(screen.getByTestId('jobs-bulk')).toHaveTextContent('1')

    const callback = getListenerCallback(
      ProjectsStore,
      ManageConstants.FILTER_PROJECTS,
    )
    act(() => callback(1, 'user', 'archived'))

    expect(screen.getByTestId('jobs-bulk')).toBeEmptyDOMElement()
    expect(screen.getByLabelText('Unarchive')).toBeInTheDocument()
    expect(screen.queryByLabelText('Archive')).not.toBeInTheDocument()
  })

  test('shows the cancelled action set when the filter switches to cancelled', () => {
    renderComponent()
    const callback = getListenerCallback(
      ProjectsStore,
      ManageConstants.FILTER_PROJECTS,
    )
    act(() => callback(1, 'user', 'cancelled'))

    expect(screen.getByLabelText('Resume')).toBeInTheDocument()
    expect(screen.getByLabelText('Delete permanently')).toBeInTheDocument()
  })

  test('clears the selection when the user switches team', () => {
    renderComponent()
    fireEvent.click(screen.getByText('check-job-1'))
    expect(screen.getByTestId('jobs-bulk')).toHaveTextContent('1')

    const callback = getListenerCallback(UserStore, UserConstants.CHOOSE_TEAM)
    act(() => callback())

    expect(screen.getByTestId('jobs-bulk')).toBeEmptyDOMElement()
  })

  test('removes store listeners on unmount', () => {
    const {unmount} = renderComponent()
    unmount()
    expect(ProjectsStore.removeListener).toHaveBeenCalledWith(
      ManageConstants.FILTER_PROJECTS,
      expect.any(Function),
    )
    expect(UserStore.removeListener).toHaveBeenCalledWith(
      UserConstants.CHOOSE_TEAM,
      expect.any(Function),
    )
  })
})

describe('ProjectsBulkActions button enablement', () => {
  test('disables bulk action buttons when nothing is selected', () => {
    renderComponent()
    expect(screen.getByLabelText('Archive')).toBeDisabled()
  })

  test('enables bulk action buttons once a job is selected', () => {
    renderComponent()
    fireEvent.click(screen.getByText('check-job-1'))
    expect(screen.getByLabelText('Archive')).toBeEnabled()
  })

  test('disables Move to team / Assign to member when a project is only partially selected', () => {
    renderComponent()
    fireEvent.click(screen.getByText('check-job-1'))
    expect(
      screen.getByLabelText(
        'Move to team - Some projects are only partially selected. Select all jobs to enable this action.',
      ),
    ).toBeDisabled()
    expect(
      screen.getByLabelText(
        'Assign to member - Some projects are only partially selected. Select all jobs to enable this action.',
      ),
    ).toBeDisabled()
  })

  test('disables Assign to member when the active team is personal', () => {
    renderComponent({isSelectedTeamPersonal: true})
    fireEvent.click(screen.getByLabelText('Select all visible jobs'))
    expect(
      screen.getByLabelText(
        'Assign to member - Open a different team to enable this action.',
      ),
    ).toBeDisabled()
  })

  test('disables Assign to member when selected projects span different teams', () => {
    renderComponent()
    fireEvent.click(screen.getByLabelText('Select all visible jobs'))
    expect(
      screen.getByLabelText(
        'Assign to member - Some projects are only partially selected. Select all jobs to enable this action.',
      ),
    ).toBeDisabled()
    expect(screen.getByLabelText('Move to team')).toBeEnabled()
  })
})

describe('ProjectsBulkActions submit flows', () => {
  test('archiving fewer than 10 jobs submits immediately without a confirm modal', () => {
    renderComponent()
    fireEvent.click(screen.getByText('check-job-1'))
    fireEvent.click(screen.getByLabelText('Archive'))

    expect(ModalsActions.showModalComponent).not.toHaveBeenCalled()
    expect(ManageActions.changeJobStatus).toHaveBeenCalledTimes(1)
    const [projectArg, jobArg, statusArg] =
      ManageActions.changeJobStatus.mock.calls[0]
    expect(projectArg.get('id')).toBe(100)
    expect(jobArg.get('id')).toBe(1)
    expect(statusArg).toBe('archive')
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Jobs archived'}),
    )
    expect(screen.getByTestId('jobs-bulk')).toHaveTextContent('')
  })

  test('cancelling 10 or more jobs opens a confirm modal, and confirming submits it', () => {
    const manyJobs = Array.from({length: 10}, (_, i) => ({
      id: i + 1,
      password: `pass${i + 1}`,
      status: 'active',
    }))
    const bigProject = {id: 200, id_team: 1, jobs: manyJobs}
    render(
      <ProjectsBulkActions
        projects={[bigProject]}
        teams={teams}
        isSelectedTeamPersonal={false}
      >
        <TestConsumer />
      </ProjectsBulkActions>,
    )
    fireEvent.click(screen.getByLabelText('Select all visible jobs'))
    fireEvent.click(screen.getByLabelText('Cancel'))

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      ConfirmMessageModal,
      expect.objectContaining({
        text: 'You are about to cancel 10 jobs. Are you sure you want to proceed?',
        successText: 'Continue',
        cancelText: 'Cancel',
        successCallback: expect.any(Function),
      }),
      'Confirmation required',
    )
    expect(ManageActions.changeJobStatus).not.toHaveBeenCalled()

    const {successCallback} = ModalsActions.showModalComponent.mock.calls[0][1]
    successCallback()

    expect(ManageActions.changeJobStatus).toHaveBeenCalledTimes(10)
  })

  test('generating revise 2 skips jobs that already have two revise passwords', () => {
    const alreadyGenerated = {
      id: 4,
      password: 'p4',
      revise_passwords: ['a', 'b'],
    }
    const project = {id: 300, id_team: 1, jobs: [job1, alreadyGenerated]}
    render(
      <ProjectsBulkActions
        projects={[project]}
        teams={teams}
        isSelectedTeamPersonal={false}
      >
        <TestConsumer />
      </ProjectsBulkActions>,
    )
    fireEvent.click(screen.getByLabelText('Select all visible jobs'))
    fireEvent.click(screen.getByLabelText('Generate revise 2'))

    expect(ManageActions.getSecondPassReview).toHaveBeenCalledTimes(1)
    expect(ManageActions.getSecondPassReview).toHaveBeenCalledWith(
      300,
      undefined,
      1,
      'pass1',
    )
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Revise 2 links generated'}),
    )
  })

  test('changing password opens BulkChangePassword and applies the result on success', async () => {
    changeJobPassword.mockResolvedValue({
      id: '1',
      new_pwd: 'new-pass',
      old_pwd: 'pass1',
    })
    renderComponent()
    fireEvent.click(screen.getByText('check-job-1'))
    fireEvent.click(screen.getByLabelText('Change password'))

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      BulkChangePassword,
      expect.objectContaining({title: 'Change password', teams}),
      'Change password',
    )

    const {successCallback} = ModalsActions.showModalComponent.mock.calls[0][1]
    successCallback({revision_number: undefined})

    await waitFor(() =>
      expect(ManageActions.changeJobPassword).toHaveBeenCalledWith(
        expect.anything(),
        expect.anything(),
        'new-pass',
        'pass1',
        undefined,
      ),
    )
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Translate passwords changed'}),
    )
    expect(ModalsActions.onCloseModal).toHaveBeenCalled()
  })

  test('moving to a team opens BulkMoveToTeam and applies the result on success', () => {
    renderComponent()
    fireEvent.click(screen.getByLabelText('Select all visible jobs'))
    fireEvent.click(screen.getByLabelText('Move to team'))

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      BulkMoveToTeam,
      expect.objectContaining({title: 'Move to team'}),
      'Move to team',
    )

    const {successCallback} = ModalsActions.showModalComponent.mock.calls[0][1]
    successCallback({id_team: 5})

    expect(ManageActions.changeProjectsTeamBulk).toHaveBeenCalledWith(
      5,
      expect.any(Array),
    )
    expect(ModalsActions.onCloseModal).toHaveBeenCalled()
  })

  test('assigning to a member opens BulkAssignToMember and applies the result on success', () => {
    renderComponent()
    fireEvent.click(screen.getByText('check-project-100'))
    fireEvent.click(screen.getByLabelText('Assign to member'))

    expect(ModalsActions.showModalComponent).toHaveBeenCalledWith(
      BulkAssignToMember,
      expect.objectContaining({title: 'Assign to member'}),
      'Assign to member',
    )

    const {successCallback} = ModalsActions.showModalComponent.mock.calls[0][1]
    successCallback({id_assignee: 42})

    expect(ManageActions.changeProjectAssigneeBulk).toHaveBeenCalledWith(
      42,
      expect.any(Array),
      teams,
    )
  })
})
