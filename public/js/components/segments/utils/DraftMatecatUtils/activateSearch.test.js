import {ContentState} from 'draft-js'
import activateSearch from './activateSearch'
import * as DraftMatecatConstants from './editorConstants'

describe('activateSearch', () => {
  test('returns a decorator descriptor with search name/component', () => {
    const occurrences = []
    const decorator = activateSearch(
      'hello',
      {ingnoreCase: true, exactMatch: false},
      occurrences,
      0,
      [],
    )
    expect(decorator.name).toBe(DraftMatecatConstants.SEARCH_DECORATOR)
    expect(decorator.component).toBeDefined()
    expect(typeof decorator.strategy).toBe('function')
  })

  test('strategy highlights matches of the search term in the block', () => {
    const decorator = activateSearch(
      'world',
      {ingnoreCase: true, exactMatch: false},
      [],
      0,
      [],
    )
    const callback = jest.fn()
    const contentBlock = ContentState.createFromText('hello world').getFirstBlock()

    decorator.strategy(contentBlock, callback)

    expect(callback).toHaveBeenCalled()
    const [start, end] = callback.mock.calls[0]
    expect(contentBlock.getText().slice(start, end)).toBe('world')
  })

  test('strategy does not invoke callback when the search text is empty', () => {
    const decorator = activateSearch(
      '',
      {ingnoreCase: true, exactMatch: false},
      [],
      0,
      [],
    )
    const callback = jest.fn()
    decorator.strategy(
      ContentState.createFromText('hello world').getFirstBlock(),
      callback,
    )
    expect(callback).not.toHaveBeenCalled()
  })

  test('fills in an unset occurrence entry with the match position and block key', () => {
    // activateSearch clones the occurrences array internally, so bookkeeping
    // mutations must be read back from decorator.props.occurrences
    const decorator = activateSearch(
      'hello',
      {ingnoreCase: true, exactMatch: false},
      [{}],
      0,
      [],
    )
    const contentBlock = ContentState.createFromText('hello world').getFirstBlock()
    decorator.strategy(contentBlock, jest.fn())

    expect(decorator.props.occurrences[0]).toMatchObject({
      key: contentBlock.getKey(),
      start: 0,
      end: 5,
    })
  })

  test('assigns the match to the next entry without a key when the current one belongs elsewhere', () => {
    const decorator = activateSearch(
      'hello',
      {ingnoreCase: true, exactMatch: false},
      [{key: 'some-other-block', start: 1, end: 2}, {}],
      0,
      [],
    )
    const contentBlock = ContentState.createFromText('hello world').getFirstBlock()
    decorator.strategy(contentBlock, jest.fn())

    expect(decorator.props.occurrences[1]).toMatchObject({
      key: contentBlock.getKey(),
      start: 0,
      end: 5,
    })
  })

  test('stops processing further matches once no unassigned occurrence slot remains', () => {
    const decorator = activateSearch(
      'hello',
      {ingnoreCase: true, exactMatch: false},
      [{key: 'some-other-block', start: 1, end: 2}],
      0,
      [],
    )
    const callback = jest.fn()
    const contentBlock = ContentState.createFromText(
      'hello world hello again',
    ).getFirstBlock()

    decorator.strategy(contentBlock, callback)

    // the first match hits the "no unassigned slot" branch and returns early,
    // so no highlight callback ever fires for this block
    expect(callback).not.toHaveBeenCalled()
    expect(decorator.props.occurrences[0]).toMatchObject({
      key: 'some-other-block',
      start: 1,
      end: 2,
    })
  })
})
