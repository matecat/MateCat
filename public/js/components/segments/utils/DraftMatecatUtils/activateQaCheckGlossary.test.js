import {ContentState} from 'draft-js'
import activateQaCheckGlossary from './activateQaCheckGlossary'
import * as DraftMatecatConstants from './editorConstants'

describe('activateQaCheckGlossary', () => {
  test('returns a decorator descriptor carrying missingTerms/sid props', () => {
    const decorator = activateQaCheckGlossary([], 'text', 'sid-1')
    expect(decorator.name).toBe(DraftMatecatConstants.QA_GLOSSARY_DECORATOR)
    expect(decorator.props).toEqual({missingTerms: [], sid: 'sid-1'})
  })

  test('strategy highlights matching missing-glossary words', () => {
    const missingTerms = [{matching_words: ['world']}]
    const decorator = activateQaCheckGlossary(missingTerms, 'hello world', 'sid-1')
    const callback = jest.fn()
    const contentBlock = ContentState.createFromText('hello world').getFirstBlock()

    decorator.strategy(contentBlock, callback)

    expect(callback).toHaveBeenCalled()
    const [start, end] = callback.mock.calls[0]
    expect(contentBlock.getText().slice(start, end)).toBe('world')
  })

  test('does not invoke callback when there are no missing terms', () => {
    const decorator = activateQaCheckGlossary([], 'hello world', 'sid-1')
    const callback = jest.fn()
    decorator.strategy(
      ContentState.createFromText('hello world').getFirstBlock(),
      callback,
    )
    expect(callback).not.toHaveBeenCalled()
  })
})
