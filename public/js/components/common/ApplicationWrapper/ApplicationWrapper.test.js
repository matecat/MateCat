import React from 'react'
import {render, screen, act, waitFor} from '@testing-library/react'
import {ApplicationWrapper} from './ApplicationWrapper'
import useAuth from '../../../hooks/useAuth'
import {onModalWindowMounted} from '../../modals/ModalWindow'
import CommonUtils from '../../../utils/commonUtils'
import ModalsActions from '../../../actions/ModalsActions'
import UserStore from '../../../stores/UserStore'
import UserConstants from '../../../constants/UserConstants'

jest.mock('../../../hooks/useAuth')
jest.mock('../../modals/ModalWindow', () => ({
  onModalWindowMounted: jest.fn(),
}))
jest.mock('../../../utils/commonUtils', () => ({
  lookupFlashServiceParam: jest.fn(),
}))
jest.mock('../../../actions/ModalsActions', () => ({
  openResetPassword: jest.fn(),
  openSuccessModal: jest.fn(),
  openLoginModal: jest.fn(),
  openRegisterModal: jest.fn(),
}))
jest.mock('../../../stores/UserStore', () => ({
  addListener: jest.fn(),
  removeListener: jest.fn(),
}))

const baseAuth = {
  isUserLogged: true,
  userInfo: {user: {uid: 1}},
  connectedServices: [],
  userDisconnected: false,
  setUserInfo: jest.fn(),
  logout: jest.fn(),
  forceLogout: jest.fn(),
  setUserMetadataKey: jest.fn(),
}

describe('ApplicationWrapper', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    useAuth.mockReturnValue(baseAuth)
    onModalWindowMounted.mockResolvedValue(undefined)
    CommonUtils.lookupFlashServiceParam.mockReturnValue(undefined)
  })

  it('renders children', () => {
    render(
      <ApplicationWrapper>
        <div>my child content</div>
      </ApplicationWrapper>,
    )
    expect(screen.getByText('my child content')).toBeInTheDocument()
  })

  it('does not render a forced action modal by default', () => {
    render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    expect(screen.queryByText('Please Sign in again')).not.toBeInTheDocument()
    expect(screen.queryByText('Update Required')).not.toBeInTheDocument()
  })

  it('renders the disconnect modal when userDisconnected is true', () => {
    useAuth.mockReturnValue({...baseAuth, userDisconnected: true})
    render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    expect(screen.getByText('Please Sign in again')).toBeInTheDocument()
  })

  it('registers a FORCE_RELOAD listener on UserStore on mount', () => {
    render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    expect(UserStore.addListener).toHaveBeenCalledWith(
      UserConstants.FORCE_RELOAD,
      expect.any(Function),
    )
  })

  it('removes the FORCE_RELOAD listener on unmount', () => {
    const {unmount} = render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    unmount()
    expect(UserStore.removeListener).toHaveBeenCalledWith(
      UserConstants.FORCE_RELOAD,
      expect.any(Function),
    )
  })

  it('renders the reload modal after the FORCE_RELOAD event fires', () => {
    render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    const forceReloadFn = UserStore.addListener.mock.calls[0][1]
    act(() => forceReloadFn())
    expect(screen.getByText('Update Required')).toBeInTheDocument()
  })

  it('does not show the reload modal when userDisconnected is true, even after FORCE_RELOAD', () => {
    useAuth.mockReturnValue({...baseAuth, userDisconnected: true})
    render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    const forceReloadFn = UserStore.addListener.mock.calls[0][1]
    act(() => forceReloadFn())
    expect(screen.queryByText('Update Required')).not.toBeInTheDocument()
    expect(screen.getByText('Please Sign in again')).toBeInTheDocument()
  })

  it('does nothing when there is no flash popup param', async () => {
    render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    await act(async () => {})
    expect(ModalsActions.openLoginModal).not.toHaveBeenCalled()
    expect(ModalsActions.openRegisterModal).not.toHaveBeenCalled()
    expect(ModalsActions.openResetPassword).not.toHaveBeenCalled()
    expect(ModalsActions.openSuccessModal).not.toHaveBeenCalled()
  })

  it('opens the login modal when the flash popup param is "login"', async () => {
    CommonUtils.lookupFlashServiceParam.mockReturnValue([{value: 'login'}])
    render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    await act(async () => {})
    expect(ModalsActions.openLoginModal).toHaveBeenCalled()
  })

  it('opens the register modal when the flash popup param is "signup"', async () => {
    CommonUtils.lookupFlashServiceParam.mockReturnValue([{value: 'signup'}])
    render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    await act(async () => {})
    expect(ModalsActions.openRegisterModal).toHaveBeenCalled()
  })

  it('opens the reset password modal when the flash popup param is "passwordReset"', async () => {
    CommonUtils.lookupFlashServiceParam.mockReturnValue([
      {value: 'passwordReset'},
    ])
    render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    await act(async () => {})
    expect(ModalsActions.openResetPassword).toHaveBeenCalledWith({
      setNewPassword: true,
    })
  })

  it('opens the success modal when the flash popup param is "profile"', async () => {
    CommonUtils.lookupFlashServiceParam.mockReturnValue([{value: 'profile'}])
    render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    await act(async () => {})
    expect(ModalsActions.openSuccessModal).toHaveBeenCalledWith({
      title: 'Registration complete',
      text: 'You are now logged in and ready to use Matecat.',
    })
  })

  it('does nothing for an unrecognized flash popup value', async () => {
    CommonUtils.lookupFlashServiceParam.mockReturnValue([
      {value: 'something-else'},
    ])
    render(
      <ApplicationWrapper>
        <div>child</div>
      </ApplicationWrapper>,
    )
    await act(async () => {})
    expect(ModalsActions.openLoginModal).not.toHaveBeenCalled()
    expect(ModalsActions.openRegisterModal).not.toHaveBeenCalled()
    expect(ModalsActions.openResetPassword).not.toHaveBeenCalled()
    expect(ModalsActions.openSuccessModal).not.toHaveBeenCalled()
  })
})
