import activateLexiqa from './activateLexiqa'
import * as DraftMatecatConstants from './editorConstants'

describe('activateLexiqa', () => {
  test('returns a decorator descriptor with the lexiqa name/component', () => {
    const getUpdatedSegmentInfo = jest.fn()
    const replaceWordAt = jest.fn()
    const decorator = activateLexiqa(
      null,
      [],
      'sid-1',
      true,
      getUpdatedSegmentInfo,
      replaceWordAt,
    )
    expect(decorator.name).toBe(DraftMatecatConstants.LEXIQA_DECORATOR)
    expect(decorator.props).toEqual({
      warnings: [],
      sid: 'sid-1',
      isSource: true,
      getUpdatedSegmentInfo,
      replaceWordAt,
    })
    expect(typeof decorator.strategy).toBe('function')
    expect(decorator.component).toBeDefined()
  })

  test('strategy invokes callback for a warning matching the block key', () => {
    const warnings = [{blockKey: 'block-1', start: 0, end: 3}]
    const decorator = activateLexiqa(null, warnings, 'sid-1', false)
    const callback = jest.fn()
    const contentBlock = {
      getKey: () => 'block-1',
      getEntityAt: () => null,
    }
    const contentState = {getEntity: jest.fn()}

    decorator.strategy(contentBlock, callback, contentState)

    expect(callback).toHaveBeenCalledWith(0, 3)
  })

  test('strategy does not invoke callback for a warning on a different block', () => {
    const warnings = [{blockKey: 'other-block', start: 0, end: 3}]
    const decorator = activateLexiqa(null, warnings, 'sid-1', false)
    const callback = jest.fn()
    const contentBlock = {getKey: () => 'block-1', getEntityAt: () => null}

    decorator.strategy(contentBlock, callback, {getEntity: jest.fn()})

    expect(callback).not.toHaveBeenCalled()
  })
})
