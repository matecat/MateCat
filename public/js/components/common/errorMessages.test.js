import * as ErrorMessages from './errorMessages'

describe('errorMessages', () => {
  test('isRequired', () => {
    expect(ErrorMessages.isRequired('Name')).toBe('Name is required')
  })

  test('mustMatch', () => {
    expect(ErrorMessages.mustMatch('Password')('Confirm password')).toBe(
      'Confirm password must match Password',
    )
  })

  test('minLength', () => {
    expect(ErrorMessages.minLength(8)('Password')).toBe(
      'Password must be at least 8 characters',
    )
  })

  test('maxLength', () => {
    expect(ErrorMessages.maxLength(20)('Password')).toBe(
      'Password can have a maximum of 20 characters',
    )
  })

  test('atLeastOneSpecialChar', () => {
    expect(ErrorMessages.atLeastOneSpecialChar()('Password')).toBe(
      'Password must contain at least one special character: ' +
        ' !"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~',
    )
  })

  test('validEmail', () => {
    expect(ErrorMessages.validEmail('Email')).toBe(
      'Insert a valid email address',
    )
  })
})
