/**
 * @jest-environment node
 */
import path from 'path'
import * as sass from 'sass'
import postcss from 'postcss'

// The API docs body is centred, and #contentBox has to put its content back
// on the left. It used to inherit that from a global `.wrapper` rule, which
// went away with common.scss and left the whole page centred. jsdom does no
// layout, so the contract is asserted on the compiled stylesheet instead.
const SASS_ROOT = path.resolve(__dirname, '../../')

const declarationsOf = (root, selector) => {
  const declarations = {}
  root.walkRules((rule) => {
    if (!rule.selectors.includes(selector)) return
    rule.walkDecls((decl) => {
      declarations[decl.prop] = decl.important
        ? `${decl.value} !important`
        : decl.value
    })
  })
  return declarations
}

describe('ApiDocPage.scss layout', () => {
  let root

  beforeAll(() => {
    const {css} = sass.compile(path.join(__dirname, 'ApiDocPage.scss'), {
      loadPaths: [SASS_ROOT],
      quietDeps: true,
      logger: sass.Logger.silent,
    })
    root = postcss.parse(css)
  })

  test('the content box is left-aligned inside a centred column', () => {
    expect(declarationsOf(root, '#contentBox')).toMatchObject({
      'text-align': 'left',
      width: '92%',
      margin: '0 auto',
    })
  })

  // A class selector, because the shared body rule loads after this sheet and
  // would win a tie on `body`.
  test('the page sets its own font size on .api', () => {
    expect(declarationsOf(root, '.api')).toMatchObject({
      'font-size': '14px',
    })
  })
})
