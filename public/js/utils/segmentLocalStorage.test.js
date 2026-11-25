import {
  getLastSegmentFromLocalStorage,
  setLastSegmentFromLocalStorage,
} from './segmentLocalStorage'

beforeEach(() => {
  localStorage.clear()
  global.config = {...global.config, id_job: 2, password: 'pass'}
})

test('getLastSegmentFromLocalStorage returns null when nothing is stored', () => {
  expect(getLastSegmentFromLocalStorage()).toBeNull()
})

test('setLastSegmentFromLocalStorage stores the segment id under the job/password key', () => {
  setLastSegmentFromLocalStorage(42)

  expect(getLastSegmentFromLocalStorage()).toBe('42')
  expect(localStorage.getItem('currentSegmentId-2pass')).toBe('42')
})

test('setLastSegmentFromLocalStorage clears matching keys and retries when storage throws', () => {
  localStorage.setItem('currentSegmentId-old', 'stale')
  const setItemSpy = jest
    .spyOn(Storage.prototype, 'setItem')
    .mockImplementationOnce(() => {
      throw new Error('QuotaExceededError')
    })

  setLastSegmentFromLocalStorage(99)

  expect(setItemSpy).toHaveBeenCalledTimes(2)
  expect(localStorage.getItem('currentSegmentId-old')).toBeNull()
  expect(getLastSegmentFromLocalStorage()).toBe('99')

  setItemSpy.mockRestore()
})
