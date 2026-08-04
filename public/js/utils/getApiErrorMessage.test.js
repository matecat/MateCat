import {getApiErrorMessage} from './getApiErrorMessage'

const FALLBACK = 'Something went wrong. Please try again.'

describe('getApiErrorMessage', () => {
  test('returns the message from the errors envelope the backend renders for a 4xx', () => {
    const rejection = {
      response: {status: 400},
      errors: [
        {
          code: 400,
          message: 'Wrong parameter: name cannot contain a URL or a domain name',
        },
      ],
    }

    expect(getApiErrorMessage(rejection)).toBe(
      'Wrong parameter: name cannot contain a URL or a domain name',
    )
  })

  test('joins every message when the envelope carries more than one', () => {
    const rejection = {
      errors: [{message: 'First problem.'}, {message: 'Second problem.'}],
    }

    expect(getApiErrorMessage(rejection)).toBe('First problem. Second problem.')
  })

  test('reads a message off a bare object, since the api clients fall back to the whole body', () => {
    expect(getApiErrorMessage({errors: {message: 'Plain message'}})).toBe(
      'Plain message',
    )
  })

  test('falls back when the response had no body to reject with', () => {
    expect(getApiErrorMessage({response: {status: 500}})).toBe(FALLBACK)
  })

  test.each([
    ['nothing at all', undefined],
    ['null', null],
    ['an empty errors array', {errors: []}],
    ['entries without a message', {errors: [{code: 400}]}],
    ['an empty message', {errors: [{message: ''}]}],
    ['a non-string message', {errors: [{message: 42}]}],
  ])('falls back given %s', (_label, rejection) => {
    expect(getApiErrorMessage(rejection)).toBe(FALLBACK)
  })

  test('allows the caller to choose the fallback', () => {
    expect(getApiErrorMessage({}, 'Could not rename the team.')).toBe(
      'Could not rename the team.',
    )
  })
})
