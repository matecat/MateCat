import CryptoJS from 'crypto-js'

export class MemoizeRequest {
  constructor(limit = 10) {
    this.cache = []
    this.LIMIT = limit
  }

  getKey(params) {
    const normalized = this.stableStringify(params)
    return CryptoJS.SHA256(normalized).toString()
  }

  get(params) {
    return this.cache.find(({key}) => key === this.getKey(params))?.value
  }

  set(params, value) {
    const key = this.getKey(params)

    if (!this.has(key)) this.cache.push({key, value})

    if (this.cache.length > this.LIMIT) this.cache.shift()
  }

  has(key) {
    return this.cache.some((item) => item.key === key)
  }

  stableStringify(obj) {
    if (obj === null || typeof obj !== 'object') {
      return JSON.stringify(obj)
    }

    if (Array.isArray(obj)) {
      return `[${obj.map(this.stableStringify).join('|')}]`
    }

    return `{${Object.keys(obj)
      .sort()
      .map((k) => `${k}:${this.stableStringify(obj[k])}`)
      .join('|')}}`
  }
}
