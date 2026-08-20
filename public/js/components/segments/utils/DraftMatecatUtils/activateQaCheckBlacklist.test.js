import {ContentState} from 'draft-js'
import activateQaCheckBlacklist from './activateQaCheckBlacklist'
import * as DraftMatecatConstants from './editorConstants'

describe('activateQaCheckBlacklist', () => {
  test('returns a decorator descriptor carrying blacklist/sid props', () => {
    const decorator = activateQaCheckBlacklist([], 'sid-1')
    expect(decorator.name).toBe(DraftMatecatConstants.QA_BLACKLIST_DECORATOR)
    expect(decorator.props).toEqual({blackListedTerms: [], sid: 'sid-1'})
  })

  test('strategy highlights matching blacklisted words', () => {
    const blackListedTerms = [{matching_words: ['bad']}]
    const decorator = activateQaCheckBlacklist(blackListedTerms, 'sid-1')
    const callback = jest.fn()
    const contentBlock = ContentState.createFromText('this is bad text').getFirstBlock()

    decorator.strategy(contentBlock, callback)

    expect(callback).toHaveBeenCalled()
    const [start, end] = callback.mock.calls[0]
    expect(contentBlock.getText().slice(start, end)).toBe('bad')
  })

  test('does not invoke callback when the blacklist is empty', () => {
    const decorator = activateQaCheckBlacklist([], 'sid-1')
    const callback = jest.fn()
    decorator.strategy(
      ContentState.createFromText('nothing to see here').getFirstBlock(),
      callback,
    )
    expect(callback).not.toHaveBeenCalled()
  })
})
