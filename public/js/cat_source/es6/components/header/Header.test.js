import {act, render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'
import {http, HttpResponse} from 'msw'
import {createRoot} from 'react-dom/client'

import {mswServer} from '../../../../../mocks/mswServer'
import Header from './Header'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'
import userMock from '../../../../../mocks/userMock'

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
const props = {
  fromLanguage: true,
  languagesList: true,
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
      pending_invitations: ['fede@translated.net'],
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
      http.get('/api/app/user', () => {
        return HttpResponse.json(apiUserMockResponse)
      }),
      http.get('/api/app/api-key/show', () => {
        return HttpResponse.json({errors: ['The user has not a valid API key']})
      }),
      http.post('/api/app/user/logout', () => {
        return HttpResponse.json({})
      }),
    ],
  )
}

test('Rendering elements', async () => {
  const user = userEvent.setup()

  executeMswServer()
  render(
    <ApplicationWrapperContext.Provider
      value={{isUserLogged: true, userInfo: userMock}}
    >
      <Header {...props} />
    </ApplicationWrapperContext.Provider>,
  )

  expect(screen.getByTestId('logo')).toBeInTheDocument()

  await waitFor(() => {
    expect(screen.getByTestId('user-menu-metadata')).toBeInTheDocument()
  })
  await act(async () => user.click(screen.getByTestId('user-menu-metadata')))
  expect(screen.getByText('Profile')).toBeInTheDocument()
  expect(screen.getByText('Logout')).toBeInTheDocument()
  expect(screen.getByText('Logout')).toBeEnabled()
  expect(screen.getByTestId('team-select')).toBeInTheDocument()
})

xtest('Click profile from user menu', async () => {
  const user = userEvent.setup()
  executeMswServer()

  render(
    <ApplicationWrapperContext.Provider
      value={{isUserLogged: true, userInfo: userMock}}
    >
      <Header {...props} />
    </ApplicationWrapperContext.Provider>,
  )
  await act(async () => user.click(screen.getByTestId('user-menu-metadata')))

  await act(async () => user.click(screen.getByText('Profile')))
  expect(screen.getByTestId('preferences-modal')).toBeInTheDocument()
})
