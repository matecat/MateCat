import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import PreferencesModal from './PreferencesModal'
import {getUserApiKey} from '../../api/getUserApiKey'
import {createUserApiKey} from '../../api/createUserApiKey'
import {deleteUserApiKey} from '../../api/deleteUserApiKey'
import {connectedServicesGDrive} from '../../api/connectedServicesGDrive'
import {modifyUserInfo} from '../../api/modifyUserInfo/modifyUser'
import {getUserData} from '../../api/getUserData'
import UserActions from '../../actions/UserActions'
import UserStore from '../../stores/UserStore'
import ModalsActions from '../../actions/ModalsActions'
import {ApplicationWrapperContext} from '../common/ApplicationWrapper/ApplicationWrapperContext'

jest.mock('../../api/getUserApiKey')
jest.mock('../../api/createUserApiKey')
jest.mock('../../api/deleteUserApiKey')
jest.mock('../../api/connectedServicesGDrive')
jest.mock('../../api/modifyUserInfo/modifyUser')
jest.mock('../../api/getUserData')
jest.mock('../../actions/UserActions')
jest.mock('../../stores/UserStore')
jest.mock('../../actions/ModalsActions')

const baseUserInfo = () => ({
  user: {
    uid: 1,
    first_name: 'John',
    last_name: 'Doe',
    email: 'john@doe.com',
    has_password: true,
  },
  metadata: null,
  connected_services: [],
})

const renderModal = ({
  userInfo = baseUserInfo(),
  setUserInfo = jest.fn(),
  logout = jest.fn(),
  props = {},
} = {}) => {
  return render(
    <ApplicationWrapperContext.Provider value={{userInfo, setUserInfo, logout}}>
      <PreferencesModal {...props} />
    </ApplicationWrapperContext.Provider>,
  )
}

const originalConfig = {...global.config}

beforeEach(() => {
  global.config = {
    ...originalConfig,
    isAnInternalUser: false,
    googleDriveEnabled: false,
    userShortName: 'JD',
    gdriveAuthURL: 'https://gdrive.example',
  }
  UserStore.getDefaultConnectedService.mockReturnValue(undefined)
  getUserApiKey.mockResolvedValue(null)
})

afterEach(() => {
  jest.clearAllMocks()
  global.config = {...originalConfig}
})

test('shows the "no api key" message when the user has no credentials and is not internal', async () => {
  getUserApiKey.mockResolvedValue(null)
  renderModal()

  await waitFor(() =>
    expect(
      screen.getByText(/There is no API key associated to your account/),
    ).toBeInTheDocument(),
  )
  expect(screen.queryByText('Generate')).not.toBeInTheDocument()
})

test('internal users can generate, copy and delete an API key', async () => {
  getUserApiKey.mockResolvedValue(null)
  global.config.isAnInternalUser = true
  createUserApiKey.mockResolvedValue({
    api_key: 'key123',
    api_secret: 'secret123',
  })
  Object.assign(navigator, {
    clipboard: {writeText: jest.fn()},
  })
  deleteUserApiKey.mockResolvedValue()

  renderModal()

  await waitFor(() =>
    expect(screen.getByText('No API Key associated to your account')),
  )
  fireEvent.click(screen.getByText('Generate'))

  await waitFor(() => expect(screen.getByDisplayValue('key123')))
  expect(screen.getByDisplayValue('secret123')).toBeInTheDocument()

  fireEvent.click(screen.getByText('Copy'))
  expect(navigator.clipboard.writeText).toHaveBeenCalledWith('key123-secret123')
  expect(screen.getByText('Copied')).toBeInTheDocument()

  fireEvent.click(screen.getByText('Delete'))
  expect(
    screen.getByText('Are you sure you want to delete the token?'),
  ).toBeInTheDocument()

  fireEvent.click(screen.getByText('Cancel'))
  expect(
    screen.queryByText('Are you sure you want to delete the token?'),
  ).not.toBeInTheDocument()

  fireEvent.click(screen.getByText('Delete'))
  fireEvent.click(screen.getByText('Delete'))

  await waitFor(() => expect(deleteUserApiKey).toHaveBeenCalledTimes(1))
  await waitFor(() =>
    expect(
      screen.getByText('No API Key associated to your account'),
    ).toBeInTheDocument(),
  )
})

test('shows existing credentials as a read-only textarea when not freshly created', async () => {
  getUserApiKey.mockResolvedValue({
    api_key: 'existingKey',
    api_secret: 'existingSecret',
  })
  renderModal()

  await waitFor(() =>
    expect(
      screen.getByDisplayValue('existingKey-existingSecret'),
    ).toBeInTheDocument(),
  )
  expect(
    screen.getByText(/An API key associated to your account is already/),
  ).toBeInTheDocument()
})

test('internal users see a Delete button for pre-existing credentials', async () => {
  getUserApiKey.mockResolvedValue({
    api_key: 'existingKey',
    api_secret: 'existingSecret',
  })
  global.config.isAnInternalUser = true
  renderModal()

  await waitFor(() => screen.getByText('Delete'))
  expect(screen.getByText('Delete')).toBeInTheDocument()
})

test('reset password link only shows when the user has a password, and triggers the action', async () => {
  const {rerender} = renderModal()
  await waitFor(() => screen.getByText('Reset Password'))
  fireEvent.click(screen.getByText('Reset Password'))
  expect(ModalsActions.openResetPassword).toHaveBeenCalledTimes(1)

  rerender(
    <ApplicationWrapperContext.Provider
      value={{
        userInfo: {
          ...baseUserInfo(),
          user: {...baseUserInfo().user, has_password: false},
        },
        setUserInfo: jest.fn(),
        logout: jest.fn(),
      }}
    >
      <PreferencesModal />
    </ApplicationWrapperContext.Provider>,
  )
  expect(screen.queryByText('Reset Password')).not.toBeInTheDocument()
})

test('clicking Logout calls the context logout function', async () => {
  const logout = jest.fn()
  renderModal({logout})
  await waitFor(() => screen.getByText('Logout'))

  fireEvent.click(screen.getByText('Logout'))
  expect(logout).toHaveBeenCalledTimes(1)
})

test('shows the connected user picture when metadata has an oauth avatar', async () => {
  const userInfo = {
    ...baseUserInfo(),
    metadata: {oauth_provider: 'google', google_picture: 'pic.png'},
  }
  const {container} = renderModal({userInfo})
  await waitFor(() => screen.getByText('John Doe'))
  expect(container.querySelector('img')).toHaveAttribute('src', 'pic.png')
})

test('modifying the user name calls modifyUserInfo and updates the context', async () => {
  modifyUserInfo.mockResolvedValue()
  const setUserInfo = jest.fn()
  renderModal({setUserInfo})

  await waitFor(() => screen.getByText('John Doe'))
  fireEvent.click(screen.getByText('John Doe').nextSibling)

  const [firstNameInput, lastNameInput] = screen.getAllByRole('textbox')
  fireEvent.change(firstNameInput, {target: {value: 'Jane'}})
  fireEvent.change(lastNameInput, {target: {value: 'Roe'}})

  fireEvent.click(screen.getByText('Confirm'))

  expect(modifyUserInfo).toHaveBeenCalledWith('Jane', 'Roe')
  expect(setUserInfo).toHaveBeenCalled()
})

test('canceling the name edit restores the original values without calling the API', async () => {
  renderModal()
  await waitFor(() => screen.getByText('John Doe'))
  fireEvent.click(screen.getByText('John Doe').nextSibling)

  const [firstNameInput] = screen.getAllByRole('textbox')
  fireEvent.change(firstNameInput, {target: {value: 'Changed'}})

  const buttons = screen.getAllByRole('button')
  const cancelIconButton = buttons[buttons.length - 1]
  fireEvent.click(cancelIconButton)

  expect(screen.getByText('John Doe')).toBeInTheDocument()
  expect(modifyUserInfo).not.toHaveBeenCalled()
})

test('the confirm button is disabled while first or last name is empty', async () => {
  renderModal()
  await waitFor(() => screen.getByText('John Doe'))
  fireEvent.click(screen.getByText('John Doe').nextSibling)

  const [firstNameInput] = screen.getAllByRole('textbox')
  fireEvent.change(firstNameInput, {target: {value: ''}})

  expect(screen.getByText('Confirm')).toBeDisabled()
})

test('pressing Enter in the name field submits the change', async () => {
  modifyUserInfo.mockResolvedValue()
  renderModal()
  await waitFor(() => screen.getByText('John Doe'))
  fireEvent.click(screen.getByText('John Doe').nextSibling)

  const [firstNameInput] = screen.getAllByRole('textbox')
  fireEvent.keyUp(firstNameInput, {key: 'Enter'})

  expect(modifyUserInfo).toHaveBeenCalled()
})

test('renders the Google Drive switch when enabled and toggles it off', async () => {
  global.config.googleDriveEnabled = true
  const service = {id: 5, email: 'me@drive.com', is_default: true}
  UserStore.getDefaultConnectedService.mockReturnValue(service)
  connectedServicesGDrive.mockResolvedValue({
    connected_service: {...service, disabled_at: '2024'},
  })
  UserStore.updateConnectedService.mockReturnValue([
    {...service, disabled_at: '2024', is_default: true},
  ])

  const userInfo = {...baseUserInfo(), connected_services: [service]}
  renderModal({userInfo})

  await waitFor(() =>
    expect(screen.getByText(/Connected to Google Drive/)).toBeInTheDocument(),
  )

  const switchInput = document.querySelector('input[name="onoffswitch"]')
  fireEvent.click(switchInput)

  await waitFor(() => expect(connectedServicesGDrive).toHaveBeenCalledWith(5))
})

test('turning the Google Drive switch on opens the auth popup', async () => {
  global.config.googleDriveEnabled = true
  UserStore.getDefaultConnectedService.mockReturnValue(undefined)
  const focusMock = jest.fn()
  window.open = jest.fn().mockReturnValue({closed: false, focus: focusMock})

  renderModal()

  await waitFor(() =>
    expect(
      screen.getByText('Allow Matecat to access your files on Google Drive'),
    ).toBeInTheDocument(),
  )

  const switchInput = document.querySelector('input[name="onoffswitch"]')
  fireEvent.click(switchInput)

  expect(window.open).toHaveBeenCalledWith(
    'https://gdrive.example',
    'name',
    'height=600,width=900',
  )
})

test('an API failure while fetching the key leaves credentials null', async () => {
  getUserApiKey.mockRejectedValue(new Error('boom'))
  renderModal()

  await waitFor(() =>
    expect(
      screen.getByText(/There is no API key associated to your account/),
    ).toBeInTheDocument(),
  )
})
