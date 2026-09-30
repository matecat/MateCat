import {ComponentExtendInterface} from './ComponentExtendInterface'

test('props getter returns the instance itself', () => {
  const instance = new ComponentExtendInterface()

  expect(instance.props).toBe(instance)
})

test('props setter assigns each entry onto the instance', () => {
  const instance = new ComponentExtendInterface()

  instance.props = {foo: 'bar', baz: 42}

  expect(instance.foo).toBe('bar')
  expect(instance.baz).toBe(42)
})

test('props setter defaults to an empty object when called without arguments', () => {
  const instance = new ComponentExtendInterface()

  expect(() => (instance.props = undefined)).not.toThrow()
})
