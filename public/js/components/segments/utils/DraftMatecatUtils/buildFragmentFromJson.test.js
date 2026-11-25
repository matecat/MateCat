import buildFragmentFromJson from './buildFragmentFromJson'

describe('buildFragmentFromJson', () => {
  test('rebuilds a block map from a plain JSON fragment description', () => {
    const fragmentObject = {
      block1: {
        key: 'block1',
        type: 'unstyled',
        text: 'ab',
        characterList: [
          {style: [], entity: null},
          {style: [], entity: null},
        ],
        depth: 0,
        data: {},
      },
    }

    const fragment = buildFragmentFromJson(fragmentObject)

    expect(fragment.get('block1').getText()).toBe('ab')
    expect(fragment.get('block1').getType()).toBe('unstyled')
  })

  test('rebuilds character style sets and entities', () => {
    const fragmentObject = {
      b1: {
        key: 'b1',
        type: 'unstyled',
        text: 'x',
        characterList: [{style: ['BOLD'], entity: 'entity-1'}],
        depth: 0,
        data: {},
      },
    }

    const fragment = buildFragmentFromJson(fragmentObject)
    const block = fragment.get('b1')
    expect(block.getEntityAt(0)).toBe('entity-1')
    expect(block.getInlineStyleAt(0).has('BOLD')).toBe(true)
  })

  test('supports multiple blocks, preserving order', () => {
    const fragmentObject = {
      b1: {
        key: 'b1',
        type: 'unstyled',
        text: 'a',
        characterList: [{style: [], entity: null}],
        depth: 0,
        data: {},
      },
      b2: {
        key: 'b2',
        type: 'unstyled',
        text: 'b',
        characterList: [{style: [], entity: null}],
        depth: 0,
        data: {},
      },
    }

    const fragment = buildFragmentFromJson(fragmentObject)
    expect(fragment.keySeq().toArray()).toEqual(['b1', 'b2'])
  })
})
