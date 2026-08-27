import React from 'react'
import {render} from '@testing-library/react'
import {List} from 'immutable'
import {CompositeDecorator} from 'draft-js'

import CompoundDecorator from './CompoundDecorator'

const makeRawDecorator = (letter, decorationsArray) => ({
  getDecorations: jest.fn(() => List(decorationsArray)),
  getComponentForKey: jest.fn(
    (key) =>
      ({children}) => (
        <mark data-testid={`${letter}-${key}`}>{children}</mark>
      ),
  ),
  getPropsForKey: jest.fn((key) => ({[`${letter}Flag`]: key})),
})

const block = {getText: () => 'hello'}
const contentState = {}

describe('CompoundDecorator constructor', () => {
  test('keeps decorators that already expose the decorator interface as-is', () => {
    const rawDecorator = makeRawDecorator('A', [null, null, null, null, null])
    const instance = new CompoundDecorator([rawDecorator])

    expect(instance.decorators[0]).toBe(rawDecorator)
  })

  test('wraps strategy/component decorators in a CompositeDecorator', () => {
    const strategyDecorator = {
      strategy: jest.fn(),
      component: () => <span />,
    }
    const instance = new CompoundDecorator([strategyDecorator])

    expect(instance.decorators[0]).toBeInstanceOf(CompositeDecorator)
  })
})

describe('CompoundDecorator.getDecorations', () => {
  test('combines per-character decorations from every sub-decorator into JSON tuples', () => {
    const decoratorA = makeRawDecorator('A', ['A', 'A', 'A', null, null])
    const decoratorB = makeRawDecorator('B', [null, null, 'B', 'B', null])
    const instance = new CompoundDecorator([decoratorA, decoratorB])

    const decorations = instance.getDecorations(block, contentState)

    expect(decorations.toJS()).toEqual([
      JSON.stringify(['A', null]),
      JSON.stringify(['A', null]),
      JSON.stringify(['A', 'B']),
      JSON.stringify([null, 'B']),
      JSON.stringify([null, null]),
    ])
    expect(decoratorA.getDecorations).toHaveBeenCalledWith(block, contentState)
    expect(decoratorB.getDecorations).toHaveBeenCalledWith(block, contentState)
  })
})

describe('CompoundDecorator.getPropsForKey', () => {
  test('collects props only from decorators with a non-null decoration', () => {
    const decoratorA = makeRawDecorator('A', ['A', null, null, null, null])
    const decoratorB = makeRawDecorator('B', [null, null, 'B', null, null])
    const instance = new CompoundDecorator([decoratorA, decoratorB])

    const key = JSON.stringify(['A', null])
    const result = instance.getPropsForKey(key)

    expect(result).toEqual({decoratorProps: [{AFlag: 'A'}, {}]})
    expect(decoratorA.getPropsForKey).toHaveBeenCalledWith('A')
    expect(decoratorB.getPropsForKey).not.toHaveBeenCalled()
  })
})

describe('CompoundDecorator.getComponentForKey', () => {
  test('renders plain children when no sub-decorator matched', () => {
    const decoratorA = makeRawDecorator('A', [null, null, null, null, null])
    const instance = new CompoundDecorator([decoratorA])

    const key = JSON.stringify([null])
    const Composed = instance.getComponentForKey(key)

    const {container} = render(
      <Composed decoratorProps={[{}]}>plain text</Composed>,
    )

    expect(container.textContent).toBe('plain text')
    expect(container.querySelector('mark')).toBeNull()
  })

  test('nests a wrapping component for each matched decoration', () => {
    const decoratorA = makeRawDecorator('A', ['A', null, null, null, null])
    const decoratorB = makeRawDecorator('B', ['B', null, null, null, null])
    const instance = new CompoundDecorator([decoratorA, decoratorB])

    const key = JSON.stringify(['A', 'B'])
    const Composed = instance.getComponentForKey(key)

    const {container} = render(
      <Composed decoratorProps={[{}, {}]}>hi</Composed>,
    )

    const outer = container.querySelector('[data-testid="B-B"]')
    const inner = container.querySelector('[data-testid="A-A"]')
    expect(outer).toBeInTheDocument()
    expect(inner).toBeInTheDocument()
    expect(outer.contains(inner)).toBe(true)
    expect(container.textContent).toBe('hi')
  })
})
