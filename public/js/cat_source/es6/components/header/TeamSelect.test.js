import {render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'
import ReactDOM from 'react-dom'
import Immutable from 'immutable'
import TeamsSelect from './TeamsSelect'
import TeamsActions from '../../actions/TeamsActions'
import ModalsActions from '../../actions/ModalsActions'
import TeamsStore from '../../stores/TeamsStore'
import TeamConstants from '../../constants/TeamConstants'
import ManageConstants from '../../constants/ManageConstants'
import {rest} from 'msw'
import {mswServer} from '../../../../../mocks/mswServer'

window.React = React
window.ReactDOM = ReactDOM
// create modal div
const modalElement = document.createElement('div')
modalElement.id = 'modal'
document.body.appendChild(modalElement)

window.config = {
  isLoggedIn: 1,
  basepath: '/',
  hostpath: 'https://dev.matecat.com',
}

require('../../components')
require('../../../../common')
require('../../../../login')
require('../../../../user_store')
require('../../../es6/ajax_utils/userAjax')
require('../../../es6/ajax_utils/teamAjax')

const fakeTeamsData = {
  threeTeams: {
    data: JSON.parse(
      '[{"id":1,"name":"Personal","type":"personal","created_at":"2021-06-23T12:51:48+02:00","created_by":1,"members":[{"id":1,"id_team":1,"user":{"uid":1,"first_name":"Pierluigi","last_name":"Di Cianni","email":"pierluigi.dicianni@translated.net","has_password":false},"user_metadata":{"gplus_picture":"https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c"},"projects":14}],"pending_invitations":[]},{"id":2,"name":"Test","type":"general","created_at":"2021-07-05T15:40:56+02:00","created_by":1,"members":[{"id":2,"id_team":2,"user":{"uid":1,"first_name":"Pierluigi","last_name":"Di Cianni","email":"pierluigi.dicianni@translated.net","has_password":false},"user_metadata":{"gplus_picture":"https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c"},"projects":1}],"pending_invitations":[]},{"id":4,"name":"Pie","type":"general","created_at":"2021-07-15T15:26:10+02:00","created_by":1,"members":[{"id":4,"id_team":4,"user":{"uid":1,"first_name":"Pierluigi","last_name":"Di Cianni","email":"pierluigi.dicianni@translated.net","has_password":false},"user_metadata":{"gplus_picture":"https://lh3.googleusercontent.com/a/AATXAJwhJzx7La8Z9rRvEuounsMpkH7TIwsOGT_a_xOb=s96-c"},"projects":0}],"pending_invitations":[]}]',
    ),
    props: {
      changeTeam: true,
      isManage: true,
      loggedUser: true,
      selectedTeamId: 1,
      showModals: true,
      showTeams: true,
    },
  },
}

const getFakeProperties = (fakeProperties) => {
  const {data, props} = fakeProperties
  const teams = Immutable.fromJS(data)

  return {
    teams,
    props: {
      ...props,
      teams,
    },
  }
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

const apiTeamsMembersMockResponse = {
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
  pending_invitations: [],
}

const executeMswServer = () => {
  mswServer.use(
    ...[
      rest.get('/api/app/user', (req, res, ctx) => {
        return res(ctx.status(200), ctx.json(apiUserMockResponse))
      }),
      rest.get('/api/v2/teams/2/members', (req, res, ctx) => {
        return res(ctx.status(200), ctx.json(apiTeamsMembersMockResponse))
      }),
    ],
  )
}

const appModalWindowCreation = () => {
  let _value = {}
  return new Promise((resolve) => {
    Object.defineProperty(window.APP, 'ModalWindow', {
      _value: {},
      get: () => _value,
      set: (value) => {
        _value = value
        resolve()
      },
    })
  })
}

// set global USER.STORE user info
window.APP.USER.STORE.user = apiUserMockResponse.user

test('Rendering elements', () => {
  const {props} = getFakeProperties(fakeTeamsData.threeTeams)
  render(<TeamsSelect {...props} />)

  expect(screen.getByText('Create New Team')).toBeInTheDocument()
  expect(screen.getByText('Personal')).toBeInTheDocument()
  expect(screen.getByText('Test')).toBeInTheDocument()
  expect(screen.getByText('Pie')).toBeInTheDocument()
})

test('Click create new team check flow', async () => {
  executeMswServer()

  // override loadUserData request
  const defaultLoadUserData = window.APP.USER.loadUserData
  window.APP.USER.loadUserData = () => ({
    done: (fn) => fn(),
  })

  const {props} = getFakeProperties(fakeTeamsData.threeTeams)
  render(<TeamsSelect {...props} />)

  await appModalWindowCreation()

  userEvent.click(screen.getByText('Create New Team'))

  await waitFor(() => {
    expect(screen.getByTestId('create-team-modal')).toBeInTheDocument()
  })

  window.APP.USER.loadUserData = defaultLoadUserData()
})

test('Click on change team', async () => {
  executeMswServer()

  // set teams state
  TeamsActions.renderTeams(apiUserMockResponse.teams)

  const {props} = getFakeProperties(fakeTeamsData.threeTeams)
  render(<TeamsSelect {...props} />)

  const defaultScrollTo = window.scrollTo
  window.scrollTo = () => {}

  let teamSelected
  TeamsStore.addListener(
    TeamConstants.UPDATE_TEAM,
    (team) => (teamSelected = team.get('name')),
  )

  userEvent.click(screen.getByTestId('Test'))

  await waitFor(() => {
    expect(teamSelected).toBe('Test')
  })

  window.scrollTo = defaultScrollTo
})

test('Click on team settings', async () => {
  executeMswServer()

  const {props} = getFakeProperties(fakeTeamsData.threeTeams)
  render(<TeamsSelect {...props} />)

  TeamsStore.addListener(
    ManageConstants.OPEN_MODIFY_TEAM_MODAL,
    (team, hideChangeName) =>
      ModalsActions.openModifyTeamModal(team, hideChangeName),
  )

  userEvent.click(screen.getByTestId('team-setting-icon-Test'))

  await waitFor(() => {
    expect(screen.getByTestId('modify-team-modal')).toBeInTheDocument()
  })
})
