import {List, Map} from 'immutable'
import ContentBlockNode from './ContentBlockNode'

describe('ContentBlockNode', () => {
  test('exposes basic getters', () => {
    const node = new ContentBlockNode({
      key: 'a',
      type: 'unstyled',
      text: 'hello',
      depth: 2,
      data: Map({foo: 'bar'}),
      children: List(['child-1']),
      parent: 'parent-key',
      prevSibling: 'prev-key',
      nextSibling: 'next-key',
    })

    expect(node.getKey()).toBe('a')
    expect(node.getType()).toBe('unstyled')
    expect(node.getText()).toBe('hello')
    expect(node.getLength()).toBe(5)
    expect(node.getDepth()).toBe(2)
    expect(node.getData()).toEqual(Map({foo: 'bar'}))
    expect(node.getChildKeys().toArray()).toEqual(['child-1'])
    expect(node.getParentKey()).toBe('parent-key')
    expect(node.getPrevSiblingKey()).toBe('prev-key')
    expect(node.getNextSiblingKey()).toBe('next-key')
  })

  test('auto-fills an empty characterList sized to the text when none is provided', () => {
    const node = new ContentBlockNode({key: 'a', text: 'abc'})
    expect(node.getCharacterList().size).toBe(3)
  })

  test('getInlineStyleAt returns the empty set for an out-of-range offset', () => {
    const node = new ContentBlockNode({key: 'a', text: 'abc'})
    expect(node.getInlineStyleAt(99).size).toBe(0)
  })

  test('getEntityAt returns null for an out-of-range offset and when unset', () => {
    const node = new ContentBlockNode({key: 'a', text: 'abc'})
    expect(node.getEntityAt(0)).toBeNull()
    expect(node.getEntityAt(99)).toBeNull()
  })

  test('findStyleRanges and findEntityRanges delegate to findRangesImmutable', () => {
    const node = new ContentBlockNode({key: 'a', text: 'abc'})
    const styleCallback = jest.fn()
    node.findStyleRanges(() => true, styleCallback)
    expect(styleCallback).toHaveBeenCalledWith(0, 3)

    const entityCallback = jest.fn()
    node.findEntityRanges(() => true, entityCallback)
    expect(entityCallback).toHaveBeenCalledWith(0, 3)
  })

  test('constructing with no props uses the default record', () => {
    const node = new ContentBlockNode()
    expect(node.getKey()).toBe('')
    expect(node.getType()).toBe('unstyled')
    expect(node.getText()).toBe('')
  })
})
