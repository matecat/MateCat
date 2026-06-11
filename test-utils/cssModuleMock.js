// CSS Module identity mock for Jest.
// Returns the class name as-is so styles['foo'] === 'foo' in tests.
// This prevents jsdom from choking on SCSS syntax (@use, &-nesting, etc.)
// that jest-transform-css passes through un-compiled via style-inject.
module.exports = new Proxy(
  {},
  {
    get(target, key) {
      if (key === '__esModule') return true
      return key
    },
  },
)
