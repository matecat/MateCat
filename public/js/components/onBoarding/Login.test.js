import React from 'react'
import {render, screen, fireEvent, waitFor, act} from '@testing-library/react'
import Login from './Login'
import {OnBoardingContext} from './OnBoardingContext'
import {loginUser} from '../../api/loginUser'

jest.mock('../../api/loginUser', () => ({
  loginUser: jest.fn(),
}))

const renderWithContext = (overrides = {}) => {
  const contextValue = {
    setStep: jest.fn(),
    redirectAfterLogin: jest.fn(),
    socialLogin: jest.fn(),
    ...overrides,
  }
  return {
    ...render(
      <OnBoardingContext.Provider value={contextValue}>
        <Login />
      </OnBoardingContext.Provider>,
    ),
    contextValue,
  }
}

const fillAndSubmit = async (
  email = 'user@example.com',
  password = 'secret123456!',
) => {
  fireEvent.change(screen.getByPlaceholderText('Email'), {
    target: {value: email},
  })
  fireEvent.change(screen.getByPlaceholderText('Password'), {
    target: {value: password},
  })
  fireEvent.click(screen.getByRole('button', {name: 'Sign in'}))
}

describe('Login', () => {
  beforeEach(() => {
    jest.clearAllMocks()
  })

  test('renders email and password fields', () => {
    renderWithContext()
    expect(screen.getByPlaceholderText('Email')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Password')).toBeInTheDocument()
  })

  test('shows a validation error when submitting an empty form', async () => {
    renderWithContext()
    fireEvent.click(screen.getByRole('button', {name: 'Sign in'}))
    expect(await screen.findAllByText('This field is mandatory')).toHaveLength(
      2,
    )
  })

  test('shows a validation error for a malformed email', async () => {
    renderWithContext()
    // "a@b" passes the native browser/jsdom input[type=email] format check
    // (so the native constraint validation doesn't silently block the
    // <form> submit event before React ever sees it) while still failing
    // the app's own EMAIL_PATTERN (no dot + TLD in the domain part), so
    // this exercises the custom react-hook-form pattern validation.
    fireEvent.change(screen.getByPlaceholderText('Email'), {
      target: {value: 'a@b'},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Sign in'}))
    expect(
      await screen.findByText('Enter a valid email address'),
    ).toBeInTheDocument()
  })

  test('calls loginUser and redirectAfterLogin on successful submit', async () => {
    loginUser.mockResolvedValue({})
    const {contextValue} = renderWithContext()
    await fillAndSubmit()
    await waitFor(() =>
      expect(contextValue.redirectAfterLogin).toHaveBeenCalled(),
    )
    expect(loginUser).toHaveBeenCalledWith('user@example.com', 'secret123456!')
  })

  test('shows "Login failed." on a non-429 rejection', async () => {
    loginUser.mockRejectedValue({status: 401})
    renderWithContext()
    await fillAndSubmit()
    expect(await screen.findByText('Login failed.')).toBeInTheDocument()
  })

  test('shows a countdown message on a 429 rejection and re-enables after it elapses', async () => {
    jest.useFakeTimers()
    // The countdown message is only ever set inside the setInterval tick
    // (never synchronously on rejection), so with Retry-After=3 the first
    // tick decrements 3 -> 2 and shows "2 seconds"; nothing is rendered
    // before that first tick fires.
    loginUser.mockRejectedValue({
      status: 429,
      headers: {get: () => '3'},
    })
    renderWithContext()
    await act(async () => {
      await fillAndSubmit()
    })

    await act(async () => {
      jest.advanceTimersByTime(1000)
    })
    expect(
      screen.getByText('Too many attempts, please retry in 2 seconds'),
    ).toBeInTheDocument()

    await act(async () => {
      jest.advanceTimersByTime(1000)
    })
    expect(
      screen.getByText('Too many attempts, please retry in 1 seconds'),
    ).toBeInTheDocument()

    await act(async () => {
      jest.advanceTimersByTime(1000)
    })
    expect(screen.queryByText(/Too many attempts/)).not.toBeInTheDocument()

    jest.useRealTimers()
  })

  test('clicking "Sign up" calls setStep(REGISTER)', () => {
    const {contextValue} = renderWithContext()
    fireEvent.click(screen.getByRole('button', {name: 'Sign up'}))
    expect(contextValue.setStep).toHaveBeenCalledWith('register')
  })

  test('clicking "Forgot your password?" calls setStep(FORGOT_PASSWORD)', () => {
    const {contextValue} = renderWithContext()
    fireEvent.click(screen.getByRole('button', {name: 'Forgot your password?'}))
    expect(contextValue.setStep).toHaveBeenCalledWith('forgotPassword')
  })
})
