import {http, HttpResponse} from 'msw'
import React from 'react'
import {screen, waitFor, render} from '@testing-library/react'

import {mswServer} from '../../../../../mocks/mswServer'
import Dashboard from './Dashboard'

xtest('renders properly', async () => {
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
      http.post('*', () => {
        const queryParams = req.url.searchParams
        const action = queryParams.get('action')

        if (action != 'getProjects') {
          throw new Error('msw :: branch not mocked, yet.')
        }

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

  require('../../../../common')
  require('../../../../user_store')

  {
    const elHeader = document.createElement('header')
    const elModal = document.createElement('div')
    elModal.id = 'modal'
    const elContainer = document.createElement('div')
    elContainer.id = 'manage-container'

    document.body.appendChild(elHeader)
    document.body.appendChild(elModal)
    document.body.appendChild(elContainer)
  }

  render(<Dashboard />)

  await waitFor(() => {
    expect(screen.getByPlaceholderText('Search by project name')).toBeVisible()
    expect(screen.getByText('Welcome to your Personal area')).toBeVisible()
  }, 2000)

  expect(screen.getByTitle('Status Filter')).toBeVisible()
  expect(screen.getByTitle('Status Filter')).toHaveTextContent(/active/)
})
