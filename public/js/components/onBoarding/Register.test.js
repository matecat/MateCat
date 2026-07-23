import React from 'react'
import {render, screen, fireEvent, waitFor} from '@testing-library/react'
import Register from './Register'
import {OnBoardingContext} from './OnBoardingContext'
import {registerUser} from '../../api/registerUser'
import {resendEmailConfirmation} from '../../api/resendEmailConfirmation/resendEmailConfirmation'
import CommonUtils from '../../utils/commonUtils'

jest.mock('../../api/registerUser', () => ({
  registerUser: jest.fn(),
}))
jest.mock('../../api/resendEmailConfirmation/resendEmailConfirmation', () => ({
  resendEmailConfirmation: jest.fn(),
}))
jest.mock('../../utils/commonUtils', () => ({
  dispatchAnalyticsEvents: jest.fn(),
}))

const VALID_PASSWORD = 'Sup3rSecret!'

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
        <Register />
      </OnBoardingContext.Provider>,
    ),
    contextValue,
  }
}

const fillValidForm = () => {
  fireEvent.change(screen.getByPlaceholderText('Name'), {target: {value: 'Ada'}})
  fireEvent.change(screen.getByPlaceholderText('Surname'), {
    target: {value: 'Lovelace'},
  })
  fireEvent.change(screen.getByPlaceholderText('Email'), {
    target: {value: 'ada@example.com'},
  })
  fireEvent.change(screen.getByPlaceholderText('Password'), {
    target: {value: VALID_PASSWORD},
  })
  fireEvent.change(screen.getByPlaceholderText('Confirm password'), {
    target: {value: VALID_PASSWORD},
  })
  fireEvent.click(screen.getByRole('checkbox'))
}

describe('Register', () => {
  beforeEach(() => jest.clearAllMocks())

  test('renders the signup form fields', () => {
    renderWithContext()
    expect(screen.getByPlaceholderText('Name')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Surname')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Email')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Password')).toBeInTheDocument()
    expect(screen.getByPlaceholderText('Confirm password')).toBeInTheDocument()
  })

  test('shows validation errors on empty submit, including unaccepted terms', async () => {
    renderWithContext()
    fireEvent.click(screen.getByRole('button', {name: 'Create account'}))
    expect(
      await screen.findByText('Please agree to the Terms of Service'),
    ).toBeInTheDocument()
    expect(screen.getAllByText('This field is mandatory').length).toBeGreaterThan(0)
  })

  test('shows a validation error when password is too short', async () => {
    renderWithContext()
    fillValidForm()
    fireEvent.change(screen.getByPlaceholderText('Password'), {
      target: {value: 'short1!'},
    })
    fireEvent.change(screen.getByPlaceholderText('Confirm password'), {
      target: {value: 'short1!'},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Create account'}))
    expect(
      await screen.findByText('Password must be at least 12 characters'),
    ).toBeInTheDocument()
  })

  test('shows a validation error when passwords do not match', async () => {
    renderWithContext()
    fillValidForm()
    fireEvent.change(screen.getByPlaceholderText('Confirm password'), {
      target: {value: 'DifferentPass1!'},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Create account'}))
    expect(await screen.findByText("Passwords don't match")).toBeInTheDocument()
  })

  test('dispatches an analytics event and calls registerUser on valid submit', async () => {
    registerUser.mockResolvedValue({})
    renderWithContext()
    fillValidForm()
    fireEvent.click(screen.getByRole('button', {name: 'Create account'}))
    await waitFor(() => expect(registerUser).toHaveBeenCalled())
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
      event: 'open_register',
      type: 'email',
    })
    expect(registerUser).toHaveBeenCalledWith({
      firstname: 'Ada',
      surname: 'Lovelace',
      email: 'ada@example.com',
      password: VALID_PASSWORD,
      passwordConfirmation: VALID_PASSWORD,
      wantedUrl: window.location.href,
    })
  })

  test('shows the confirm-registration view after a successful submit', async () => {
    registerUser.mockResolvedValue({})
    renderWithContext()
    fillValidForm()
    fireEvent.click(screen.getByRole('button', {name: 'Create account'}))
    expect(await screen.findByText('Confirm registration')).toBeInTheDocument()
    expect(screen.getByText('ada@example.com')).toBeInTheDocument()
  })

  test('shows the server error message on rejection', async () => {
    registerUser.mockRejectedValue({message: 'Email already registered'})
    renderWithContext()
    fillValidForm()
    fireEvent.click(screen.getByRole('button', {name: 'Create account'}))
    expect(await screen.findByText('Email already registered')).toBeInTheDocument()
  })

  test('shows a generic error message when rejection has no message', async () => {
    registerUser.mockRejectedValue({})
    renderWithContext()
    fillValidForm()
    fireEvent.click(screen.getByRole('button', {name: 'Create account'}))
    expect(
      await screen.findByText(/There was a problem saving the data/),
    ).toBeInTheDocument()
  })

  test('"OK" on the confirm-registration view calls redirectAfterLogin', async () => {
    registerUser.mockResolvedValue({})
    const {contextValue} = renderWithContext()
    fillValidForm()
    fireEvent.click(screen.getByRole('button', {name: 'Create account'}))
    fireEvent.click(await screen.findByRole('button', {name: 'OK'}))
    expect(contextValue.redirectAfterLogin).toHaveBeenCalled()
  })

  test('"Resend Email" calls resendEmailConfirmation and shows confirmation text', async () => {
    registerUser.mockResolvedValue({})
    resendEmailConfirmation.mockResolvedValue({})
    renderWithContext()
    fillValidForm()
    fireEvent.click(screen.getByRole('button', {name: 'Create account'}))
    fireEvent.click(await screen.findByRole('button', {name: 'Resend Email'}))
    await waitFor(() =>
      expect(resendEmailConfirmation).toHaveBeenCalledWith('ada@example.com'),
    )
    expect(await screen.findByText('Email sent again')).toBeInTheDocument()
  })

  test('clicking "Sign in" calls setStep(LOGIN)', () => {
    const {contextValue} = renderWithContext()
    fireEvent.click(screen.getByRole('button', {name: 'Sign in'}))
    expect(contextValue.setStep).toHaveBeenCalledWith('login')
  })

  test('clicking "Terms and Conditions" opens the terms page in a new tab', () => {
    window.open = jest.fn()
    renderWithContext()
    fireEvent.click(screen.getByRole('button', {name: 'Terms and Conditions'}))
    expect(window.open).toHaveBeenCalledWith(
      'https://site.matecat.com/terms/',
      '_blank',
    )
  })
})
