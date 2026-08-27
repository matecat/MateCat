import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import ForgotPassword from './ForgotPassword'
import {OnBoardingContext} from './OnBoardingContext'
import {forgotPassword} from '../../api/forgotPassword'

jest.mock('../../api/forgotPassword', () => ({
  forgotPassword: jest.fn(),
}))

const renderWithContext = (setStep = jest.fn()) =>
  render(
    <OnBoardingContext.Provider value={{setStep}}>
      <ForgotPassword />
    </OnBoardingContext.Provider>,
  )

describe('ForgotPassword', () => {
  beforeEach(() => jest.clearAllMocks())

  test('renders the email field', () => {
    renderWithContext()
    expect(screen.getByPlaceholderText('Email')).toBeInTheDocument()
  })

  test('shows validation error on empty submit', async () => {
    renderWithContext()
    fireEvent.click(screen.getByRole('button', {name: 'Send link'}))
    expect(
      await screen.findByText('This field is mandatory'),
    ).toBeInTheDocument()
  })

  test('shows the success message after a successful submit', async () => {
    forgotPassword.mockResolvedValue({})
    renderWithContext()
    fireEvent.change(screen.getByPlaceholderText('Email'), {
      target: {value: 'user@example.com'},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Send link'}))
    expect(
      await screen.findByText(/Success! Check your email/),
    ).toBeInTheDocument()
    expect(forgotPassword).toHaveBeenCalledWith(
      'user@example.com',
      window.location.href,
    )
  })

  test('shows the API error message on rejection', async () => {
    forgotPassword.mockRejectedValue({errors: [{message: 'Unknown email'}]})
    renderWithContext()
    fireEvent.change(screen.getByPlaceholderText('Email'), {
      target: {value: 'user@example.com'},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Send link'}))
    expect(await screen.findByText('Unknown email')).toBeInTheDocument()
  })

  test('shows a generic error message when rejection has no errors array', async () => {
    forgotPassword.mockRejectedValue({})
    renderWithContext()
    fireEvent.change(screen.getByPlaceholderText('Email'), {
      target: {value: 'user@example.com'},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Send link'}))
    expect(
      await screen.findByText(/There was a problem saving the data/),
    ).toBeInTheDocument()
  })

  test('"Back to sign in" from the success view calls setStep(LOGIN)', async () => {
    forgotPassword.mockResolvedValue({})
    const setStep = jest.fn()
    renderWithContext(setStep)
    fireEvent.change(screen.getByPlaceholderText('Email'), {
      target: {value: 'user@example.com'},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Send link'}))
    fireEvent.click(
      await screen.findByRole('button', {name: 'Back to sign in'}),
    )
    expect(setStep).toHaveBeenCalledWith('login')
  })
})
