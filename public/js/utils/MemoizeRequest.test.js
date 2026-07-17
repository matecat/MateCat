import {MemoizeRequest} from './MemoizeRequest'

describe('MemoizeRequest', () => {
  let memoize

  beforeEach(() => {
    memoize = new MemoizeRequest()
  })

  describe('constructor', () => {
    it('should initialize with empty cache', () => {
      expect(memoize.cache).toEqual([])
    })

    it('should initialize with default limit of 10', () => {
      expect(memoize.LIMIT).toBe(10)
    })

    it('should initialize with custom limit', () => {
      const customMemoize = new MemoizeRequest(5)
      expect(customMemoize.LIMIT).toBe(5)
    })
  })

  describe('getKey', () => {
    it('should return a string', () => {
      expect(typeof memoize.getKey({a: 1})).toBe('string')
    })

    it('should return the same key for the same params', () => {
      const key1 = memoize.getKey({a: 1, b: 2})
      const key2 = memoize.getKey({a: 1, b: 2})
      expect(key1).toBe(key2)
    })

    it('should return the same key regardless of param key order', () => {
      const key1 = memoize.getKey({a: 1, b: 2})
      const key2 = memoize.getKey({b: 2, a: 1})
      expect(key1).toBe(key2)
    })

    it('should return different keys for different params', () => {
      const key1 = memoize.getKey({a: 1})
      const key2 = memoize.getKey({a: 2})
      expect(key1).not.toBe(key2)
    })
  })

  describe('get', () => {
    it('should return undefined for non-existing key', () => {
      expect(memoize.get({a: 1})).toBeUndefined()
    })

    it('should return the value for an existing key', () => {
      memoize.set({a: 1}, 'value')
      expect(memoize.get({a: 1})).toBe('value')
    })

    it('should return undefined after cache is cleared', () => {
      memoize.set({a: 1}, 'value')
      memoize.cache = []
      expect(memoize.get({a: 1})).toBeUndefined()
    })
  })

  describe('set', () => {
    it('should add a new entry to the cache', () => {
      memoize.set({a: 1}, 'value')
      expect(memoize.cache.length).toBe(1)
    })

    it('should not add duplicate entries', () => {
      memoize.set({a: 1}, 'value')
      memoize.set({a: 1}, 'value2')
      expect(memoize.cache.length).toBe(1)
    })

    it('should evict the oldest entry when limit is exceeded', () => {
      const limitedMemoize = new MemoizeRequest(3)
      limitedMemoize.set({a: 1}, 'value1')
      limitedMemoize.set({a: 2}, 'value2')
      limitedMemoize.set({a: 3}, 'value3')
      limitedMemoize.set({a: 4}, 'value4')

      expect(limitedMemoize.cache.length).toBe(3)
      expect(limitedMemoize.get({a: 1})).toBeUndefined()
      expect(limitedMemoize.get({a: 4})).toBe('value4')
    })
  })

  describe('has', () => {
    it('should return false for non-existing key', () => {
      expect(memoize.has('nonexistentkey')).toBe(false)
    })

    it('should return true for existing key', () => {
      memoize.set({a: 1}, 'value')
      const key = memoize.getKey({a: 1})
      expect(memoize.has(key)).toBe(true)
    })
  })

  describe('stableStringify', () => {
    it('should stringify null', () => {
      expect(memoize.stableStringify(null)).toBe('null')
    })

    it('should stringify a number', () => {
      expect(memoize.stableStringify(42)).toBe('42')
    })

    it('should stringify a string', () => {
      expect(memoize.stableStringify('hello')).toBe('"hello"')
    })

    it('should stringify an array', () => {
      expect(memoize.stableStringify([1, 2, 3])).toBe('[1|2|3]')
    })

    it('should stringify an object with sorted keys', () => {
      expect(memoize.stableStringify({b: 2, a: 1})).toBe('{a:1|b:2}')
    })

    it('should stringify nested objects', () => {
      expect(memoize.stableStringify({a: {c: 3, b: 2}})).toBe('{a:{b:2|c:3}}')
    })
  })
})
