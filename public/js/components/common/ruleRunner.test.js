import {ruleRunner, run} from './ruleRunner'

describe('ruleRunner', () => {
  test('returns null when all validations pass', () => {
    const alwaysValid = () => null
    const runner = ruleRunner('name', 'Name', alwaysValid)
    expect(runner({name: 'John'})).toBeNull()
  })

  test('returns an error object keyed by field when a validation fails', () => {
    const alwaysInvalid = () => (fieldName) => `${fieldName} is wrong`
    const runner = ruleRunner('name', 'Name', alwaysInvalid)
    expect(runner({name: 'John'})).toEqual({name: 'Name is wrong'})
  })

  test('stops at the first failing validation', () => {
    const firstFails = () => (fieldName) => `${fieldName} first error`
    const secondFails = () => (fieldName) => `${fieldName} second error`
    const runner = ruleRunner('name', 'Name', firstFails, secondFails)
    expect(runner({name: 'John'})).toEqual({name: 'Name first error'})
  })

  test('passes field value and full state to each validation', () => {
    const validation = jest.fn(() => null)
    const state = {name: 'John', other: 'value'}
    const runner = ruleRunner('name', 'Name', validation)
    runner(state)
    expect(validation).toHaveBeenCalledWith('John', state)
  })
})

describe('run', () => {
  test('returns an empty object when there are no runners', () => {
    expect(run({name: 'John'}, [])).toEqual({})
  })

  test('merges results from multiple passing/failing runners', () => {
    const nameRunner = () => ({name: 'Name is required'})
    const emailRunner = () => null
    expect(run({}, [nameRunner, emailRunner])).toEqual({
      name: 'Name is required',
    })
  })

  test('combines errors from multiple failing runners', () => {
    const nameRunner = () => ({name: 'Name is required'})
    const emailRunner = () => ({email: 'Email is required'})
    expect(run({}, [nameRunner, emailRunner])).toEqual({
      name: 'Name is required',
      email: 'Email is required',
    })
  })
})
