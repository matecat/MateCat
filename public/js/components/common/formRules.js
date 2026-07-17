import * as ErrorMessages from './errorMessages.js'

export const requiredRule = (text) => {
  if (text) {
    return null
  } else {
    return ErrorMessages.isRequired
  }
}

export const mustMatch = (field, fieldName) => {
  return (text, state) => {
    return state[field] === text ? null : ErrorMessages.mustMatch(fieldName)
  }
}

export const minLength = (length) => {
  return (text) => {
    return text.length >= length ? null : ErrorMessages.minLength(length)
  }
}

export const maxLength = (length) => {
  return (text) => {
    return text.length > length ? ErrorMessages.maxLength(length) : null
  }
}

export const atLeastOneSpecialChar = (length) => {
  return (text) => {
    return text.search(/[ !"#$%&'()*+,-.\/:;<=>?@\[\]^_`{|}~]/) !== -1
      ? null
      : ErrorMessages.atLeastOneSpecialChar(length)
  }
}

export const checkEmail = (text) => {
  var re =
    /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
  if (!re.test(text.trim())) {
    return ErrorMessages.validEmail
  }
  return null
}
