const GENERIC_MESSAGE = 'Something went wrong. Please try again.'

/**
 * Pull a message worth showing out of an API rejection.
 *
 * The api/ clients reject with {response, errors}. `errors` normally comes from the
 * {errors: [{code, message}]} envelope the backend renders for a 4xx, but those clients fall
 * back to the whole body when it carries no `errors` key, and a response with an empty body
 * rejects with {response} alone. All three shapes end up here.
 */
export const getApiErrorMessage = (rejection, fallback = GENERIC_MESSAGE) => {
  const {errors} = rejection ?? {}

  if (Array.isArray(errors)) {
    const message = errors
      .map((error) => error?.message)
      .filter((message) => typeof message === 'string' && message.length > 0)
      .join(' ')

    return message.length > 0 ? message : fallback
  }

  if (typeof errors?.message === 'string' && errors.message.length > 0) {
    return errors.message
  }

  return fallback
}
