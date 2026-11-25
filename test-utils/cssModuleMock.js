// CSS Module identity mock for Jest.
// Returns the class name as-is so styles['foo'] === 'foo' in tests.
// This prevents jsdom from choking on SCSS syntax (@use, &-nesting, etc.)
// that jest-transform-css passes through un-compiled via style-inject.
// The identity proxy below must stay separate from the __esModule/default
// wrapper: some components use 'default' itself as a real class key (e.g.
// BUTTON_TYPE.DEFAULT === 'default'), so the proxy's own trap cannot special-
// case 'default' — that key has to behave like any other class name. The
// __esModule/default shape Babel's interop looks for therefore lives on an
// outer plain object instead, one level up from the proxy it resolves to.
const proxy = new Proxy(
  {},
  {
    get(target, key) {
      return key
    },
  },
)

module.exports = {
  __esModule: true,
  default: proxy,
}
