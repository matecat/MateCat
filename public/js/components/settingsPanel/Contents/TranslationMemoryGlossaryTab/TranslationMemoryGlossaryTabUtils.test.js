import {
  SPECIAL_ROWS_ID,
  isOwnerOfKey,
  orderTmKeys,
  getTmDataStructureToSendServer,
  toTemplateTmEntry,
  mergeJobAndUserTmKeys,
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

describe('toTemplateTmEntry', () => {
  const row = {
    id: 'abc',
    isActive: true,
    key: 'abc',
    name: 'My key',
    r: true,
    w: true,
    penalty: 0,
  }

  test('undoing a change gives back a saved entry that has no penalty', () => {
    const saved = {key: 'abc', name: 'My key', r: true, w: true}

    expect(toTemplateTmEntry(row, [saved])).toEqual(saved)
  })

  test('keeps a zero penalty the saved entry already has', () => {
    const saved = {key: 'abc', name: 'My key', r: true, w: true, penalty: 0}

    expect(toTemplateTmEntry(row, [saved])).toEqual(saved)
  })

  test('keeps a penalty the user set', () => {
    const saved = {key: 'abc', name: 'My key', r: true, w: true}

    expect(toTemplateTmEntry({...row, penalty: 5}, [saved])).toEqual({
      ...saved,
      penalty: 5,
    })
  })

  test('keeps the penalty of a key the template did not have', () => {
    expect(toTemplateTmEntry(row, [])).toEqual({
      key: 'abc',
      name: 'My key',
      r: true,
      w: true,
      penalty: 0,
    })
  })
})

describe('mergeJobAndUserTmKeys', () => {
  const ownerJobKey = {key: 'ownerkey', owner: true, r: 1, w: 0}
  const translatorJobKey = {key: 'mykey', owner: false, r: 1, w: 1}
  const keyringOnly = {key: 'keyringonly', owner: true, r: 0, w: 0}

  test('marks the owner job keys as locked for someone who is not the job owner', () => {
    const rows = mergeJobAndUserTmKeys({
      jobKeys: [ownerJobKey, translatorJobKey],
      userKeys: [keyringOnly],
      ownerIsMe: false,
    })

    expect(rows.map(({key, isJobOwnerKey}) => [key, isJobOwnerKey])).toEqual([
      ['ownerkey', true],
      ['mykey', false],
      ['keyringonly', undefined],
    ])
  })

  test('leaves every key editable for the job owner', () => {
    const rows = mergeJobAndUserTmKeys({
      jobKeys: [ownerJobKey],
      userKeys: [keyringOnly],
      ownerIsMe: true,
    })

    expect(rows.some(({isJobOwnerKey}) => isJobOwnerKey)).toBe(false)
  })

  test('keeps the job copy of a key that is also in the keyring', () => {
    const rows = mergeJobAndUserTmKeys({
      jobKeys: [ownerJobKey],
      userKeys: [{...ownerJobKey, r: 0, w: 0}],
      ownerIsMe: false,
    })

    expect(rows).toEqual([
      {
        ...ownerJobKey,
        id: 'ownerkey',
        isActive: true,
        isLocked: false,
        isJobOwnerKey: true,
      },
    ])
  })

  test('toTemplateTmEntry leaves the flag out of the template', () => {
    expect(
      toTemplateTmEntry({key: 'k', r: true, w: false, isJobOwnerKey: true}),
    ).toEqual({key: 'k', r: true, w: false})
  })
})
