/**
 * @jest-environment node
 */
import path from 'path'
import * as sass from 'sass'
import postcss from 'postcss'

// The open Visual context panel has no positioning of its own: it stays at
// the bottom only because the page is a viewport-high flex column in which
// `.main-container { flex: 1 }` takes the space above it. jsdom does no
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

describe('CattoolPage.scss layout', () => {
  let root

  beforeAll(() => {
    const {css} = sass.compile(path.join(__dirname, 'CattoolPage.scss'), {
      loadPaths: [SASS_ROOT],
      quietDeps: true,
      logger: sass.Logger.silent,
    })
    root = postcss.parse(css)
  })

  test('the page is constrained to the viewport', () => {
    expect(declarationsOf(root, 'body.cattool')).toMatchObject({
      height: '100vh',
      'overflow-y': 'hidden !important',
    })
  })

  test('the mount root is a flex column', () => {
    expect(declarationsOf(root, 'body.cattool .page-content')).toMatchObject({
      display: 'flex',
      'flex-direction': 'column',
    })
  })

  test('the main container fills the space above the context preview', () => {
    expect(declarationsOf(root, 'body.cattool .main-container')).toMatchObject({
      flex: '1',
    })
  })
})
