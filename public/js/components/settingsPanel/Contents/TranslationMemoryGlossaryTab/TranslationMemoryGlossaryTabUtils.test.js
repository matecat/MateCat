import {
  SPECIAL_ROWS_ID,
  isOwnerOfKey,
  orderTmKeys,
  getTmDataStructureToSendServer,
} from './TranslationMemoryGlossaryTabUtils'

describe('TranslationMemoryGlossaryTabUtils', () => {
  test('SPECIAL_ROWS_ID exposes the expected ids', () => {
    expect(SPECIAL_ROWS_ID).toEqual({
      defaultTranslationMemory: 'mmSharedKey',
      addSharedResource: 'addSharedResource',
      newResource: 'newResource',
    })
  })

  describe('isOwnerOfKey', () => {
    test('returns true for a key without asterisks', () => {
      expect(isOwnerOfKey('abc123')).toBe(true)
    })

    test('returns false for a key containing an asterisk', () => {
      expect(isOwnerOfKey('abc*123')).toBe(false)
    })
  })

  describe('orderTmKeys', () => {
    const keyA = {key: 'a'}
    const keyB = {key: 'b'}
    const keyC = {key: 'c'}

    test('returns tmKeys unchanged when keysOrdered is not an array', () => {
      const tmKeys = [keyA, keyB]
      expect(orderTmKeys(tmKeys, undefined)).toBe(tmKeys)
    })

    test('reorders tmKeys following keysOrdered', () => {
      const result = orderTmKeys([keyA, keyB, keyC], ['c', 'a', 'b'])
      expect(result).toEqual([keyC, keyA, keyB])
    })

    test('appends keys not present in keysOrdered at the end', () => {
      const result = orderTmKeys([keyA, keyB, keyC], ['b'])
      expect(result).toEqual([keyB, keyA, keyC])
    })

    test('filters out empty/falsy slots produced during reduction', () => {
      const result = orderTmKeys([keyA], ['x', 'y'])
      expect(result).toEqual([keyA])
    })
  })

  describe('getTmDataStructureToSendServer', () => {
    test('keeps only active, owned keys and serializes the expected shape', () => {
      const tmKeys = [
        {
          tm: true,
          glos: false,
          key: 'ownedActive',
          name: 'Owned Active',
          r: true,
          w: false,
          penalty: 0,
          isActive: true,
        },
        {
          tm: true,
          glos: true,
          key: 'ownedInactive',
          name: 'Owned Inactive',
          r: false,
          w: false,
          penalty: 0,
          isActive: false,
        },
        {
          tm: false,
          glos: true,
          key: 'notOwned*',
          name: 'Not Owned',
          r: true,
          w: true,
          penalty: 10,
          isActive: true,
        },
      ]

      const result = JSON.parse(
        getTmDataStructureToSendServer({tmKeys, keysOrdered: ['ownedActive']}),
      )

      expect(result).toEqual({
        ownergroup: [],
        anonymous: [],
        mine: [
          {
            tm: true,
            glos: false,
            key: 'ownedActive',
            name: 'Owned Active',
            r: true,
            w: false,
            penalty: 0,
          },
        ],
      })
    })

    test('defaults tmKeys to an empty array when not provided', () => {
      const result = JSON.parse(
        getTmDataStructureToSendServer({keysOrdered: []}),
      )
      expect(result).toEqual({ownergroup: [], anonymous: [], mine: []})
    })
  })
})
