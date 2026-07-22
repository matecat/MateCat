import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'

// `socialUrls` in OnBoardingContext.js is computed at module-evaluation time
// from `config.*`, and OnBoarding.js -> Login.js -> SocialButtons.js pulls in
// OnBoardingContext.js as part of its own import chain. ES `import` statements
// are hoisted, so any `global.config` merge written after an
// `import OnBoarding from './OnBoarding'` line (or even textually before it)
// still runs after OnBoarding's whole module graph has already been
// evaluated once. As in SocialButtons.test.js, `global.config` must be
// populated BEFORE the module graph is first required, so OnBoarding itself
// is pulled in via `require()` after the config merge instead of a static
// import.
global.config = {
  ...global.config,
  googleAuthURL: 'https://accounts.google.com/o/oauth2/auth',
  githubAuthUrl: 'https://github.com/login/oauth/authorize',
  microsoftAuthUrl: 'https://login.microsoftonline.com/authorize',
  linkedInAuthUrl: 'https://www.linkedin.com/oauth/authorize',
  facebookAuthUrl: 'https://www.facebook.com/dialog/oauth',
}

const OnBoarding = require('./OnBoarding').default
const ModalsActions = require('../../actions/ModalsActions')
const CommonUtils = require('../../utils/commonUtils')

jest.mock('../../actions/ModalsActions', () => ({
  onCloseModal: jest.fn(),
}))
jest.mock('../../utils/commonUtils', () => ({
  dispatchAnalyticsEvents: jest.fn(),
}))
jest.mock('../../api/loginUser', () => ({loginUser: jest.fn()}))
jest.mock('../../api/registerUser', () => ({registerUser: jest.fn()}))
jest.mock('../../api/resetPasswordUser', () => ({resetPasswordUser: jest.fn()}))
jest.mock('../../api/setNewUserPassword', () => ({setNewUserPassword: jest.fn()}))
jest.mock('../../api/forgotPassword', () => ({forgotPassword: jest.fn()}))
jest.mock('../../api/resendEmailConfirmation', () => ({
  resendEmailConfirmation: jest.fn(),
}))

describe('OnBoarding', () => {
  beforeEach(() => jest.clearAllMocks())

  test('defaults to the Login step', () => {
    render(<OnBoarding />)
    expect(screen.getByText('Sign in to Matecat')).toBeInTheDocument()
  })

  test('renders the Register step when step="register"', () => {
    render(<OnBoarding step="register" />)
    expect(screen.getByText('Sign up to Matecat')).toBeInTheDocument()
  })

  test('renders the ForgotPassword step when step="forgotPassword"', () => {
    render(<OnBoarding step="forgotPassword" />)
    expect(screen.getByText('Forgot password')).toBeInTheDocument()
  })

  test('renders the PasswordReset step when step="passwordReset"', () => {
    render(<OnBoarding step="passwordReset" />)
    expect(screen.getByText('Reset password')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Current password')).toBeInTheDocument()
  })

  test('renders PasswordReset in newPassword mode when step="setNewPassword"', () => {
    render(<OnBoarding step="setNewPassword" />)
    expect(screen.queryByPlaceholderText('Current password')).not.toBeInTheDocument()
  })

  test('does not render the back button outside the forgotPassword step', () => {
    const {container} = render(<OnBoarding />)
    expect(container.querySelector('.button-back')).not.toBeInTheDocument()
  })

  test('renders the back button on the forgotPassword step and returns to Login', () => {
    const {container} = render(<OnBoarding step="forgotPassword" />)
    fireEvent.click(container.querySelector('.button-back'))
    expect(screen.getByText('Sign in to Matecat')).toBeInTheDocument()
  })

  test('does not render the close button by default', () => {
    const {container} = render(<OnBoarding />)
    expect(container.querySelector('.button-close')).not.toBeInTheDocument()
  })

  test('renders the close button when isCloseButtonEnabled, and it calls ModalsActions.onCloseModal', () => {
    const {container} = render(<OnBoarding isCloseButtonEnabled={true} />)
    fireEvent.click(container.querySelector('.button-close'))
    expect(ModalsActions.onCloseModal).toHaveBeenCalled()
  })

  test('opens a popup window and dispatches an analytics event on social login', () => {
    const focus = jest.fn()
    window.open = jest.fn(() => ({focus, closed: false}))
    const {container} = render(<OnBoarding />)
    fireEvent.click(container.querySelector('.login-social-buttons button'))
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
      event: 'open_register',
      type: 'social',
    })
    expect(window.open).toHaveBeenCalled()
    expect(focus).toHaveBeenCalled()
  })

  test('reloads the page once the social login popup window closes', () => {
    // `window.location` and its `reload` method are non-configurable
    // ("Unforgeable") own properties in this jsdom version, so they cannot
    // be replaced with `Object.defineProperty` (that throws
    // "Cannot redefine property: location") or intercepted with
    // `jest.spyOn`. jsdom's real `location.reload()` is a documented no-op
    // that reports itself via a "not implemented: navigation" jsdomError,
    // which jest's default virtual console forwards to `console.error`.
    // We assert on that error as evidence that `redirectAfterLogin` really
    // called `location.reload()`, and mock `console.error` for the
    // duration of the assertion so the expected error doesn't leak into
    // the test run's console output.
    jest.useFakeTimers()
    const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {})
    let closed = false
    window.open = jest.fn(() => ({focus: jest.fn(), get closed() { return closed }}))
    const {container} = render(<OnBoarding />)
    fireEvent.click(container.querySelector('.login-social-buttons button'))
    closed = true
    jest.advanceTimersByTime(600)
    expect(consoleError).toHaveBeenCalledWith(
      expect.objectContaining({
        type: 'not implemented',
        message: expect.stringContaining('navigation'),
      }),
    )
    consoleError.mockRestore()
    jest.useRealTimers()
  })
})
