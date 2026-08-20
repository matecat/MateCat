import {render, screen, waitFor} from '@testing-library/react'
import {http, HttpResponse} from 'msw'
import React from 'react'

import {mswServer} from '../../mocks/mswServer'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import userMock from '../../mocks/userMock'

jest.mock('../sse/SocketListener', () => () => null)
jest.mock('./mountPage', () => ({mountPage: jest.fn()}))

global.config = {
  basepath: 'http://localhost/',
  project_id: 10,
  password: 'abc123',
}

import {ActivityLog} from './ActivityLog'

const renderPage = (context = {isUserLogged: true, userInfo: userMock}) =>
  render(
    <ApplicationWrapperContext.Provider value={context}>
      <ActivityLog />
    </ApplicationWrapperContext.Provider>,
  )

describe('ActivityLog', () => {
  test('loads project and activity log data, renders them', async () => {
    mswServer.use(
      http.get('*/api/v2/projects/:id/:password', () =>
        HttpResponse.json({
          project: {
            id: 10,
            name: 'Test project',
            jobs: [{id: 1, sourceTxt: 'en-US', targetTxt: 'it-IT'}],
          },
        }),
      ),
      http.get('*/api/v2/activity/project/:id/:password', () =>
        HttpResponse.json({
          1: {
            id_job: 1,
            first_name: 'John',
            last_name: 'Doe',
            action: 'created',
          },
        }),
      ),
    )

    renderPage()

    await waitFor(() =>
      expect(screen.getByText(/Activity Log project: 10/)).toBeInTheDocument(),
    )
  })

  test('renders for a non-logged user', async () => {
    mswServer.use(
      http.get('*/api/v2/projects/:id/:password', () =>
        HttpResponse.json({project: {id: 11, name: 'x', jobs: []}}),
      ),
      http.get('*/api/v2/activity/project/:id/:password', () =>
        HttpResponse.json({}),
      ),
    )

    renderPage({isUserLogged: false, userInfo: undefined})

    await waitFor(() =>
      expect(screen.getByText(/Activity Log project: 11/)).toBeInTheDocument(),
    )
  })
})
