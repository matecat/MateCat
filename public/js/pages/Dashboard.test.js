import {http, HttpResponse} from 'msw'
import React from 'react'
import {act, screen, waitFor, render} from '@testing-library/react'

import {mswServer} from '../../mocks/mswServer'
import Dashboard from './Dashboard'
import ManageActions from '../actions/ManageActions'
import ManageConstants from '../constants/ManageConstants'
import UserConstants from '../constants/UserConstants'
import AppDispatcher from '../stores/AppDispatcher'
import {fromJS} from 'immutable'

test('renders properly', async () => {
  mswServer.use(
    ...[
      http.get('*api/app/user', () => {
        return HttpResponse.json({
          user: {
            uid: 123,
            first_name: 'Bruce',
            last_name: 'Wayne',
            email: 'bruce.wayne@translated.net',
            has_password: false,
          },
          connected_services: [],
          metadata: {
            gplus_picture: 'https://fake-picture.jpg',
          },
          teams: [
            {
              id: 116065,
              name: 'Personal',
              type: 'personal',
              created_at: '2021-04-15T14:19:25+02:00',
              created_by: 96386,
              members: [
                {
                  id: 121346,
                  id_team: 116065,
                  user: {
                    uid: 96386,
                    first_name: 'Bruce',
                    last_name: 'Wayne',
                    email: 'bruce.wayne@translated.net',
                    has_password: false,
                  },
                  user_metadata: {
                    gplus_picture: 'https://fake-picture.jpg',
                  },
                  projects: 0,
                },
              ],
              pending_invitations: [],
            },
          ],
        })
      }),
      http.get('*api/v2/teams/:id/members', () => {
        return HttpResponse.json({
          members: [
            {
              id: 123,
              id_team: 123,
              user: {
                uid: 123,
                first_name: 'Bruce',
                last_name: 'Wayne',
                email: 'bruce.wayne@translated.net',
                has_password: false,
              },
              user_metadata: {
                gplus_picture: 'https://fake-picture.jpg',
              },
              projects: 0,
            },
          ],
          pending_invitations: [],
        })
      }),
      http.post('*api/app/get-projects', () => {
        return HttpResponse.json({
          errors: [],
          data: [],
          page: 1,
          pnumber: '0',
          pageStep: 10,
        })
      }),
    ],
  )

  global.config = {
    isLoggedIn: true,
  }

  {
    const elHeader = document.createElement('header')
    const elModal = document.createElement('div')
    elModal.id = 'modal'
    const elContainer = document.createElement('div')
    elContainer.id = 'manage-container'
    const elFooter = document.createElement('footer')

    document.body.appendChild(elHeader)
    document.body.appendChild(elModal)
    document.body.appendChild(elContainer)
    document.body.appendChild(elFooter)
  }

  render(<Dashboard />)

  await waitFor(
    () => {
      expect(
        screen.getByPlaceholderText('Search by project name'),
      ).toBeVisible()
      expect(screen.getByText('Welcome to your Personal area')).toBeVisible()
    },
    {timeout: 8000},
  )

  window.open = jest.fn()

  await act(async () => {
    ManageActions.reloadProjects()
    ManageActions.filterProjects('7', 'my project', 'active')
    ManageActions.openJobTMPanel(
      {source: 'en-US', target: 'it-IT', id: 1, password: 'p'},
      'test-project',
    )
    ManageActions.openJobSettings(
      {source: 'en-US', target: 'it-IT', id: 1, password: 'p'},
      'test-project',
    )
    AppDispatcher.dispatch({
      actionType: ManageConstants.UPDATE_TEAM_MEMBERS,
      team: fromJS({id: 1, members: [], pending_invitations: []}),
    })
    AppDispatcher.dispatch({
      actionType: UserConstants.RENDER_TEAMS,
      teams: [],
    })
    AppDispatcher.dispatch({
      actionType: UserConstants.CHOOSE_TEAM,
      teamId: 1,
      team: {id: 1, name: 'Test team'},
    })
    await Promise.resolve()
  })
})
