import {ContentBlock, genKey} from 'draft-js'
import {BlockMapBuilder} from 'draft-js'
import {List, Map} from 'immutable'
import randomizeBlockMapKeys from './randomizeBlockMapKeys'
import ContentBlockNode from './src/model/immutable/ContentBlockNode'

describe('randomizeBlockMapKeys - plain ContentBlock map', () => {
  test('assigns new keys to every block, preserving text/order', () => {
    const block1 = new ContentBlock({key: 'a', text: 'hello', type: 'unstyled'})
    const block2 = new ContentBlock({key: 'b', text: 'world', type: 'unstyled'})
    const blockMap = BlockMapBuilder.createFromArray([block1, block2])

    const result = randomizeBlockMapKeys(blockMap)

    const newKeys = result.keySeq().toArray()
    expect(newKeys).toHaveLength(2)
    expect(newKeys).not.toContain('a')
    expect(newKeys).not.toContain('b')
    expect(result.valueSeq().toArray().map((b) => b.getText())).toEqual([
      'hello',
      'world',
    ])
    // each block's own `key` field was updated to match its new map key
    result.forEach((block, key) => expect(block.getKey()).toBe(key))
  })
})

describe('randomizeBlockMapKeys - tree-based ContentBlockNode map', () => {
  test('remaps sibling/parent/child links to the new keys', () => {
    const parent = new ContentBlockNode({
      key: 'parent',
      text: '',
      type: 'unstyled',
      children: List(['child']),
    })
    const child = new ContentBlockNode({
      key: 'child',
      text: 'hi',
      type: 'unstyled',
      parent: 'parent',
    })
    const blockMap = BlockMapBuilder.createFromArray([parent, child])

    const result = randomizeBlockMapKeys(blockMap)

    const newParentKey = result
      .keySeq()
      .toArray()
      .find((key) => result.get(key).getText() === '')
    const newChildKey = result
      .keySeq()
      .toArray()
      .find((key) => result.get(key).getText() === 'hi')

    expect(result.get(newParentKey).getChildKeys().toArray()).toEqual([
      newChildKey,
    ])
    expect(result.get(newChildKey).getParentKey()).toBe(newParentKey)
  })

  test('links sequential root blocks via prevSibling/nextSibling', () => {
    const rootA = new ContentBlockNode({
      key: 'a',
      text: 'a',
      type: 'unstyled',
      nextSibling: 'b',
    })
    const rootB = new ContentBlockNode({
      key: 'b',
      text: 'b',
      type: 'unstyled',
      prevSibling: 'a',
    })
    const blockMap = BlockMapBuilder.createFromArray([rootA, rootB])

    const result = randomizeBlockMapKeys(blockMap)
    const keys = result.keySeq().toArray()
    const newA = keys.find((k) => result.get(k).getText() === 'a')
    const newB = keys.find((k) => result.get(k).getText() === 'b')

    expect(result.get(newA).getNextSiblingKey()).toBe(newB)
    expect(result.get(newB).getPrevSiblingKey()).toBe(newA)
  })

  test('handles a fragment referencing sibling/parent keys not present in the map', () => {
    const orphan = new ContentBlockNode({
      key: 'orphan',
      text: 'x',
      type: 'unstyled',
      nextSibling: 'ghost-next',
      prevSibling: 'ghost-prev',
      parent: 'ghost-parent',
      children: List(['ghost-child']),
    })
    const blockMap = BlockMapBuilder.createFromArray([orphan])

    expect(() => randomizeBlockMapKeys(blockMap)).not.toThrow()
    const result = randomizeBlockMapKeys(blockMap)
    expect(result.toArray()).toHaveLength(1)
  })
})
