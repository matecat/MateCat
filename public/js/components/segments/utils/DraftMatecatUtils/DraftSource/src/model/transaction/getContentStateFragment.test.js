import {ContentState, SelectionState} from 'draft-js'
import getContentStateFragment from './getContentStateFragment'

describe('getContentStateFragment', () => {
  test('slices a single block when start and end keys match', () => {
    const contentState = ContentState.createFromText('hello world')
    const blockKey = contentState.getFirstBlock().getKey()
    const selection = SelectionState.createEmpty(blockKey).merge({
      anchorOffset: 0,
      focusOffset: 5,
    })

    const fragment = getContentStateFragment(contentState, selection)

    expect(fragment.toArray()).toHaveLength(1)
    expect(fragment.first().getText()).toBe('hello')
  })

  test('slices the start block from the offset to the end, and the end block from 0 to the offset', () => {
    const contentState = ContentState.createFromText('line one\nline two')
    const blocks = contentState.getBlocksAsArray()
    const startKey = blocks[0].getKey()
    const endKey = blocks[1].getKey()
    const selection = SelectionState.createEmpty(startKey).merge({
      anchorKey: startKey,
      anchorOffset: 5,
      focusKey: endKey,
      focusOffset: 4,
    })

    const fragment = getContentStateFragment(contentState, selection)
    const texts = fragment.valueSeq().toArray().map((b) => b.getText())

    expect(texts).toEqual(['one', 'line'])
  })

  test('leaves untouched middle blocks unchanged when the selection spans 3+ blocks', () => {
    const contentState = ContentState.createFromText('a\nbbb\nc')
    const blocks = contentState.getBlocksAsArray()
    const selection = SelectionState.createEmpty(blocks[0].getKey()).merge({
      anchorKey: blocks[0].getKey(),
      anchorOffset: 0,
      focusKey: blocks[2].getKey(),
      focusOffset: 1,
    })

    const fragment = getContentStateFragment(contentState, selection)
    const texts = fragment.valueSeq().toArray().map((b) => b.getText())

    expect(texts).toEqual(['a', 'bbb', 'c'])
  })
})
