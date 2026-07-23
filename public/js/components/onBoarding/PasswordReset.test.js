import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import PasswordReset from './PasswordReset'
import {OnBoardingContext} from './OnBoardingContext'
import {resetPasswordUser} from '../../api/resetPasswordUser'
import {setNewUserPassword} from '../../api/setNewUserPassword'
import ModalsActions from '../../actions/ModalsActions'

jest.mock('../../api/resetPasswordUser', () => ({
  resetPasswordUser: jest.fn(),
}))
jest.mock('../../api/setNewUserPassword', () => ({
  setNewUserPassword: jest.fn(),
}))
jest.mock('../../actions/ModalsActions', () => ({
  onCloseModal: jest.fn(),
}))

const VALID_PASSWORD = 'Sup3rSecret!'

const renderWithContext = (props = {}, setStep = jest.fn()) =>
  render(
    <OnBoardingContext.Provider value={{setStep}}>
      <PasswordReset {...props} />
    </OnBoardingContext.Provider>,
  )

describe('PasswordReset - change password mode (newPassword=false)', () => {
  beforeEach(() => jest.clearAllMocks())

  test('renders current, new and confirm password fields', () => {
    renderWithContext()
    expect(
      screen.getByPlaceholderText('Current password'),
    ).toBeInTheDocument()
    expect(screen.getByPlaceholderText('New password')).toBeInTheDocument()
    expect(
      screen.getByPlaceholderText('Confirm new password'),
    ).toBeInTheDocument()
  })

  test('shows a mismatch error when confirm password does not match', async () => {
    renderWithContext()
    fireEvent.change(screen.getByPlaceholderText('Current password'), {
      target: {value: 'oldpass1234!'},
    })
    fireEvent.change(screen.getByPlaceholderText('New password'), {
      target: {value: VALID_PASSWORD},
    })
    fireEvent.change(screen.getByPlaceholderText('Confirm new password'), {
      target: {value: 'different1234!'},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Reset'}))
    expect(
      await screen.findByText("Passwords don't match"),
    ).toBeInTheDocument()
  })

  test('calls resetPasswordUser and shows success on valid submit', async () => {
    resetPasswordUser.mockResolvedValue({})
    renderWithContext()
    fireEvent.change(screen.getByPlaceholderText('Current password'), {
      target: {value: 'oldpass1234!'},
    })
    fireEvent.change(screen.getByPlaceholderText('New password'), {
      target: {value: VALID_PASSWORD},
    })
    fireEvent.change(screen.getByPlaceholderText('Confirm new password'), {
      target: {value: VALID_PASSWORD},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Reset'}))
    expect(
      await screen.findByText(/Your password has been changed/),
    ).toBeInTheDocument()
    expect(resetPasswordUser).toHaveBeenCalledWith(
      'oldpass1234!',
      VALID_PASSWORD,
      VALID_PASSWORD,
    )
  })

  test('shows the API error message on rejection', async () => {
    resetPasswordUser.mockRejectedValue([
      {code: 0, message: 'Wrong current password'},
    ])
    renderWithContext()
    fireEvent.change(screen.getByPlaceholderText('Current password'), {
      target: {value: 'wrongpass123!'},
    })
    fireEvent.change(screen.getByPlaceholderText('New password'), {
      target: {value: VALID_PASSWORD},
    })
    fireEvent.change(screen.getByPlaceholderText('Confirm new password'), {
      target: {value: VALID_PASSWORD},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Reset'}))
    expect(
      await screen.findByText('Wrong current password'),
    ).toBeInTheDocument()
  })

  test('"Close" from the success view calls ModalsActions.onCloseModal', async () => {
    resetPasswordUser.mockResolvedValue({})
    renderWithContext()
    fireEvent.change(screen.getByPlaceholderText('Current password'), {
      target: {value: 'oldpass1234!'},
    })
    fireEvent.change(screen.getByPlaceholderText('New password'), {
      target: {value: VALID_PASSWORD},
    })
    fireEvent.change(screen.getByPlaceholderText('Confirm new password'), {
      target: {value: VALID_PASSWORD},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Reset'}))
    fireEvent.click(await screen.findByRole('button', {name: 'Close'}))
    expect(ModalsActions.onCloseModal).toHaveBeenCalled()
  })
})

describe('PasswordReset - set new password mode (newPassword=true)', () => {
  beforeEach(() => jest.clearAllMocks())

  test('does not render the current password field', () => {
    renderWithContext({newPassword: true})
    expect(
      screen.queryByPlaceholderText('Current password'),
    ).not.toBeInTheDocument()
    expect(screen.getByPlaceholderText('New password')).toBeInTheDocument()
  })

  test('calls setNewUserPassword (not resetPasswordUser) on valid submit', async () => {
    setNewUserPassword.mockResolvedValue({})
    renderWithContext({newPassword: true})
    fireEvent.change(screen.getByPlaceholderText('New password'), {
      target: {value: VALID_PASSWORD},
    })
    fireEvent.change(screen.getByPlaceholderText('Confirm new password'), {
      target: {value: VALID_PASSWORD},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Reset'}))
    expect(
      await screen.findByText(/Your password has been changed/),
    ).toBeInTheDocument()
    expect(setNewUserPassword).toHaveBeenCalledWith(
      VALID_PASSWORD,
      VALID_PASSWORD,
    )
    expect(resetPasswordUser).not.toHaveBeenCalled()
  })

  test('"Back to sign in" from the success view calls setStep(LOGIN)', async () => {
    setNewUserPassword.mockResolvedValue({})
    const setStep = jest.fn()
    renderWithContext({newPassword: true}, setStep)
    fireEvent.change(screen.getByPlaceholderText('New password'), {
      target: {value: VALID_PASSWORD},
    })
    fireEvent.change(screen.getByPlaceholderText('Confirm new password'), {
      target: {value: VALID_PASSWORD},
    })
    fireEvent.click(screen.getByRole('button', {name: 'Reset'}))
    fireEvent.click(
      await screen.findByRole('button', {name: 'Back to sign in'}),
    )
    expect(setStep).toHaveBeenCalledWith('login')
  })
})
