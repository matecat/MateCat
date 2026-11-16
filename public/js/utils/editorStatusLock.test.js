import {
  fromStatusLockId,
  getPretranslateParams,
  getStatusLockOptions,
  PRETRANSLATE_STATUS_OPTIONS,
  toStatusLockId,
} from './editorStatusLock'

describe('editorStatusLock', () => {
  test('maps a (status, lock) pair to a select value', () => {
    expect(toStatusLockId({status: 'TRANSLATED', lock: false})).toBe(
      'TRANSLATED',
    )
    expect(toStatusLockId({status: 'APPROVED', lock: true})).toBe(
      'APPROVED_locked',
    )
    expect(toStatusLockId({status: 'approved2', lock: true})).toBe(
      'approved2_locked',
    )
  })

  test('never maps draft or new to a locked value', () => {
    expect(toStatusLockId({status: 'draft', lock: true})).toBe('draft')
    expect(toStatusLockId({status: 'new', lock: true})).toBe('new')
  })

  test('maps a select value back to a (status, lock) pair', () => {
    expect(fromStatusLockId('TRANSLATED')).toEqual({
      status: 'TRANSLATED',
      lock: false,
    })
    expect(fromStatusLockId('APPROVED2_locked')).toEqual({
      status: 'APPROVED2',
      lock: true,
    })
    expect(fromStatusLockId('draft')).toEqual({status: 'draft', lock: false})
  })

  test('round-trips every pre-translate option', () => {
    PRETRANSLATE_STATUS_OPTIONS.forEach(({id}) =>
      expect(toStatusLockId(fromStatusLockId(id))).toBe(id),
    )
  })

  test('lists the six pre-translate values in order', () => {
    expect(PRETRANSLATE_STATUS_OPTIONS).toEqual([
      {id: 'TRANSLATED', name: 'Translated'},
      {id: 'TRANSLATED_locked', name: 'Translated (locked)'},
      {id: 'APPROVED', name: 'Approved'},
      {id: 'APPROVED_locked', name: 'Approved (locked)'},
      {id: 'APPROVED2', name: 'Approved 2'},
      {id: 'APPROVED2_locked', name: 'Approved 2 (locked)'},
    ])
  })

  test('adds no locked variant for draft', () => {
    expect(
      getStatusLockOptions(['draft', 'translated'], (s) => s).map(({id}) => id),
    ).toEqual(['draft', 'translated', 'translated_locked'])
  })

  test('builds the flat create-project params', () => {
    expect(
      getPretranslateParams({
        match_101: {enabled: true, status: 'APPROVED', lock: true},
        match_100: {enabled: false, status: 'TRANSLATED', lock: false},
      }),
    ).toEqual({
      pretranslate_101: 1,
      pretranslate_101_status: 'APPROVED',
      pretranslate_101_lock: 1,
      pretranslate_100: 0,
      pretranslate_100_status: 'TRANSLATED',
      pretranslate_100_lock: 0,
    })
  })
})
