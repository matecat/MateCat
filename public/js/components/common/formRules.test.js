import {
  requiredRule,
  mustMatch,
  minLength,
  maxLength,
  atLeastOneSpecialChar,
  checkEmail,
} from './formRules'

describe('requiredRule', () => {
  test('returns null when text is present', () => {
    expect(requiredRule('hello')).toBeNull()
  })

  test('returns the isRequired error message function when text is falsy', () => {
    const errorFn = requiredRule('')
    expect(typeof errorFn).toBe('function')
    expect(errorFn('Field')).toBe('Field is required')
  })
})

describe('mustMatch', () => {
  test('returns null when field matches state value', () => {
    const rule = mustMatch('password', 'Password')
    expect(rule('secret', {password: 'secret'})).toBeNull()
  })

  test('returns an error message function when field does not match state value', () => {
    const rule = mustMatch('password', 'Password')
    const errorFn = rule('other', {password: 'secret'})
    expect(typeof errorFn).toBe('function')
    expect(errorFn('Confirm password')).toBe(
      'Confirm password must match Password',
    )
  })
})

describe('minLength', () => {
  test('returns null when text length is >= length', () => {
    expect(minLength(3)('abc')).toBeNull()
    expect(minLength(3)('abcd')).toBeNull()
  })

  test('returns an error message function when text is shorter than length', () => {
    const errorFn = minLength(3)('ab')
    expect(typeof errorFn).toBe('function')
    expect(errorFn('Field')).toBe('Field must be at least 3 characters')
  })
})

describe('maxLength', () => {
  test('returns null when text length is <= length', () => {
    expect(maxLength(5)('abc')).toBeNull()
    expect(maxLength(5)('abcde')).toBeNull()
  })

  test('returns an error message function when text is longer than length', () => {
    const errorFn = maxLength(3)('abcd')
    expect(typeof errorFn).toBe('function')
    expect(errorFn('Field')).toBe('Field can have a maximum of 3 characters')
  })
})

describe('atLeastOneSpecialChar', () => {
  test('returns null when text contains a special character', () => {
    expect(atLeastOneSpecialChar()('abc!')).toBeNull()
  })

  test('returns an error message function when text has no special character', () => {
    const errorFn = atLeastOneSpecialChar()('abc')
    expect(typeof errorFn).toBe('function')
    expect(errorFn('Field')).toMatch(
      /must contain at least one special character/,
    )
  })
})

describe('checkEmail', () => {
  test('returns null for a valid email address', () => {
    expect(checkEmail('john@example.com')).toBeNull()
  })

  test('trims whitespace before validating', () => {
    expect(checkEmail('  john@example.com  ')).toBeNull()
  })

  test('returns the validEmail error message function for an invalid email address', () => {
    const errorFn = checkEmail('not-an-email')
    expect(typeof errorFn).toBe('function')
    expect(errorFn('Field')).toBe('Insert a valid email address')
  })
})
