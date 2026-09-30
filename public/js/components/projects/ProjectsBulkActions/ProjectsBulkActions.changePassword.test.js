import React from 'react'
import {render, screen, fireEvent, waitFor, act} from '@testing-library/react'
import {http, HttpResponse} from 'msw'

import {ProjectsBulkActions} from './ProjectsBulkActions'
import {mswServer} from '../../../../mocks/mswServer'
import CatToolActions from '../../../actions/CatToolActions'
import ManageActions from '../../../actions/ManageActions'
import ModalsActions from '../../../actions/ModalsActions'

jest.mock('../../../actions/CatToolActions', () => ({
  __esModule: true,
  default: {
    addNotification: jest.fn(),
  },
}))

jest.mock('../../../actions/ManageActions', () => ({
  __esModule: true,
  default: {
    changeJobPassword: jest.fn(),
    changeProjectsTeamBulk: jest.fn(),
    changeProjectAssigneeBulk: jest.fn(),
  },
}))

jest.mock('../../../actions/ModalsActions', () => ({
  __esModule: true,
  default: {
    showModalComponent: jest.fn(),
    onCloseModal: jest.fn(),
  },
}))

const jobWithRevise = (id) => ({
  id,
  password: `t-${id}`,
  source: 'en-US',
  target: 'it-IT',
  revise_passwords: [{revision_number: 1, password: `r-${id}`}],
})

const TEAMS = [{id: 7, type: 'general'}]

const PROJECTS = [
  {
    id: 900,
    id_team: 7,
    name: 'Handbook',
    jobs: [jobWithRevise(1), jobWithRevise(2)],
  },
  {
    id: 901,
    id_team: 7,
    name: 'Release notes',
    // No second phase to revoke: this job can only be reported as unchanged.
    jobs: [{...jobWithRevise(3), revise_passwords: []}],
  },
]

// The rotation answers per job, so the handler decides success or failure from the posted id.
const changePasswordHandler = (statusById) =>
  http.post('/api/app/change-password', async ({request}) => {
    const posted = await request.formData()
    const id = Number(posted.get('id'))
    const status = statusById[id] ?? 200
    if (status !== 200) return new HttpResponse(null, {status})
    return HttpResponse.json({
      id: String(id),
      new_pwd: `new-${id}`,
      old_pwd: posted.get('password'),
    })
  })

const errorNotifications = () =>
  CatToolActions.addNotification.mock.calls
    .map(([notification]) => notification)
    .filter((notification) => notification.type === 'error')

const runBulkPasswordChange = async (rest, projects = PROJECTS) => {
  render(<ProjectsBulkActions projects={projects} teams={TEAMS} />)

  fireEvent.click(screen.getByLabelText('Select all visible jobs'))
  fireEvent.click(screen.getByLabelText('Change password'))

  const [[, modalProps]] = ModalsActions.showModalComponent.mock.calls
  await act(async () => {
    modalProps.successCallback(rest)
  })
}

describe('ProjectsBulkActions', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    config.basepath = '/'
    config.enableMultiDomainApi = false
  })

  test('a bulk revise password change that goes through raises no error notification', async () => {
    mswServer.use(changePasswordHandler({}))

    // Only the project whose jobs all have a Revise 1 phase: nothing here can be left behind.
    await runBulkPasswordChange({revision_number: 1}, [PROJECTS[0]])

    await waitFor(() =>
      expect(ManageActions.changeJobPassword).toHaveBeenCalledTimes(2),
    )
    expect(errorNotifications()).toHaveLength(0)
    expect(CatToolActions.addNotification).toHaveBeenCalledWith(
      expect.objectContaining({title: 'Revise passwords changed'}),
    )
  })

  test('one notification names every job that kept its old revise password', async () => {
    mswServer.use(changePasswordHandler({2: 500}))

    await runBulkPasswordChange({revision_number: 1})

    await waitFor(() => expect(errorNotifications()).toHaveLength(1))

    const [notification] = errorNotifications()
    expect(notification.title).toBe('Error change jobs password')
    expect(notification.autoDismiss).toBe(false)
    expect(notification.position).toBe('bl')

    const {getByText, queryByText} = render(<div>{notification.text}</div>)
    expect(getByText('2 of 3 jobs kept the old password:')).toBeInTheDocument()
    expect(
      getByText(
        'Release notes — job 3 (en-US > it-IT): no Revise 1 phase on this job',
      ),
    ).toBeInTheDocument()
    expect(
      getByText('Handbook — job 2 (en-US > it-IT): server answered 500'),
    ).toBeInTheDocument()
    // The job that was rotated must not be reported as still holding its old password.
    expect(queryByText(/job 1 /)).not.toBeInTheDocument()
  })

  test('a job whose rotation is refused with errors is named with the reason it was refused', async () => {
    mswServer.use(
      http.post('/api/app/change-password', async ({request}) => {
        const posted = await request.formData()
        if (Number(posted.get('id')) === 1)
          return HttpResponse.json({
            errors: [{message: 'the password does not open this job'}],
          })
        return HttpResponse.json({
          id: posted.get('id'),
          new_pwd: 'new',
          old_pwd: posted.get('password'),
        })
      }),
    )

    await runBulkPasswordChange({})

    await waitFor(() => expect(errorNotifications()).toHaveLength(1))

    const {getByText} = render(<div>{errorNotifications()[0].text}</div>)
    expect(getByText('1 of 3 jobs kept the old password:')).toBeInTheDocument()
    expect(
      getByText(
        'Handbook — job 1 (en-US > it-IT): the password does not open this job',
      ),
    ).toBeInTheDocument()
  })
})
