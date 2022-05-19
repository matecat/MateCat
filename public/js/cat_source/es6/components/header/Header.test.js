import {render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'
import {rest} from 'msw'
import {createRoot} from 'react-dom/client'

import {mswServer} from '../../../../../mocks/mswServer'
import Header from './Header'

// create modal div
const modalElement = document.createElement('div')
modalElement.id = 'modal'
document.body.appendChild(modalElement)
const mountPoint = createRoot(modalElement)
afterAll(() => mountPoint.unmount())

window.config = {
  isLoggedIn: 1,
  userShortName: 'PD',
  basepath: '/',
  hostpath: 'https://dev.matecat.com',
}
require('../../../../common')
require('../../../../login')
require('../../../../user_store')

const props = {
  fromLanguage: true,
  languagesList: true,
  loggedUser: true,
  onClose: true,
  onConfirm: true,
  selectedLanguagesFromDropdown: false,
  showFilterProjects: true,
  user: {},
}

const apiUserMockResponse = {
  user: {
    uid: 1,
    first_name: 'Pierluigi',
    last_name: 'Di Cianni',
    email: 'pierluigi.dicianni@translated.net',
    has_password: false,
  },
  connected_services: [],
  metadata: {
    gplus_picture:
      'https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c',
  },
  teams: [
    {
      id: 1,
      name: 'Personal',
      type: 'personal',
      created_at: '2021-06-23T12:51:48+02:00',
      created_by: 1,
      members: [
        {
          id: 1,
          id_team: 1,
          user: {
            uid: 1,
            first_name: 'Pierluigi',
            last_name: 'Di Cianni',
            email: 'pierluigi.dicianni@translated.net',
            has_password: false,
          },
          user_metadata: {
            gplus_picture:
              'https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c',
          },
          projects: 14,
        },
      ],
      pending_invitations: [],
    },
    {
      id: 2,
      name: 'Test',
      type: 'general',
      created_at: '2021-07-05T15:40:56+02:00',
      created_by: 1,
      members: [
        {
          id: 2,
          id_team: 2,
          user: {
            uid: 1,
            first_name: 'Pierluigi',
            last_name: 'Di Cianni',
            email: 'pierluigi.dicianni@translated.net',
            has_password: false,
          },
          user_metadata: {
            gplus_picture:
              'https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c',
          },
          projects: 1,
        },
      ],
      pending_invitations: ['federico@translated.net'],
    },
    {
      id: 4,
      name: 'Pie',
      type: 'general',
      created_at: '2021-07-15T15:26:10+02:00',
      created_by: 1,
      members: [
        {
          id: 4,
          id_team: 4,
          user: {
            uid: 1,
            first_name: 'Pierluigi',
            last_name: 'Di Cianni',
            email: 'pierluigi.dicianni@translated.net',
            has_password: false,
          },
          user_metadata: {
            gplus_picture:
              'https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c',
          },
          projects: 0,
        },
      ],
      pending_invitations: [],
    },
  ],
}

const executeMswServer = () => {
  mswServer.use(
    ...[
      rest.get('/api/app/user', (req, res, ctx) => {
        return res(ctx.status(200), ctx.json(apiUserMockResponse))
      }),
      rest.get('/api/app/api-key/show', (req, res, ctx) => {
        return res(
          ctx.status(200),
          ctx.json({errors: ['The user has not a valid API key']}),
        )
      }),
      rest.post('/api/app/user/logout', (req, res, ctx) => {
        return res(ctx.status(200), ctx.json({}))
      }),
    ],
  )
}

test('Rendering elements', async () => {
  executeMswServer()
  render(<Header {...props} />)

  expect(screen.getByTestId('logo')).toBeInTheDocument()

  await waitFor(() => {
    expect(screen.getByTestId('user-menu-metadata')).toBeInTheDocument()
    expect(screen.getByText('Profile')).toBeInTheDocument()
    expect(screen.getByText('Logout')).toBeInTheDocument()
    expect(screen.getByText('Logout')).toBeEnabled()
    expect(screen.getByTestId('team-select')).toBeInTheDocument()
  })
})

xtest('Click profile from user menu', async () => {
  executeMswServer()

  render(<Header {...props} />)

  // await waitFor(() => {
  userEvent.click(screen.getByTestId('profile-item'))
  expect(screen.getByTestId('preferences-modal')).toBeInTheDocument()
  // })
})
