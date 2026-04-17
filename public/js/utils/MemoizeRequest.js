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
    if (!this.has(params)) this.cache.push({key: this.getKey(params), value})

    if (this.cache.length > this.LIMIT) this.cache.shift()
  }

  has(params) {
    return this.cache.some(({key}) => key === this.getKey(params))
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
