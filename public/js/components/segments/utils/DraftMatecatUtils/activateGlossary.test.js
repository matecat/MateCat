import {ContentState} from 'draft-js'
import {activateGlossary} from './activateGlossary'
import * as DraftMatecatConstants from './editorConstants'

describe('activateGlossary', () => {
  test('returns a decorator with an empty-noop strategy when there are no glossary matches', () => {
    const decorator = activateGlossary([], 'sid-1')
    expect(decorator.name).toBe(DraftMatecatConstants.GLOSSARY_DECORATOR)
    expect(decorator.props).toEqual({glossary: [], sid: 'sid-1'})

    const callback = jest.fn()
    const contentBlock = ContentState.createFromText('hello world').getFirstBlock()
    decorator.strategy(contentBlock, callback)
    expect(callback).not.toHaveBeenCalled()
  })

  test('strategy highlights matching glossary words in the block text', () => {
    const glossary = [{matching_words: ['hello'], missingTerm: false}]
    const decorator = activateGlossary(glossary, 'sid-1')

    const callback = jest.fn()
    const contentBlock = ContentState.createFromText('hello world').getFirstBlock()
    decorator.strategy(contentBlock, callback)

    expect(callback).toHaveBeenCalled()
    const [start, end] = callback.mock.calls[0]
    expect(contentBlock.getText().slice(start, end)).toBe('hello')
  })

  test('skips glossary entries flagged as missingTerm', () => {
    const glossary = [{matching_words: ['hello'], missingTerm: true}]
    const decorator = activateGlossary(glossary, 'sid-1')
    const callback = jest.fn()
    decorator.strategy(
      ContentState.createFromText('hello world').getFirstBlock(),
      callback,
    )
    expect(callback).not.toHaveBeenCalled()
  })
})
