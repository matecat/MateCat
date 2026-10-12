/**
 * @jest-environment node
 */
import path from 'path'
import * as sass from 'sass'
import postcss from 'postcss'

// The maintenance and configuration-missing pages (body.offline) lost their
// illustration and heading styles when common.scss went away, leaving bare
// browser headings under the header. jsdom does no layout, so the contract is
// asserted on the compiled stylesheet instead.
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

describe('OfflinePage.scss', () => {
  let root

  beforeAll(() => {
    const {css} = sass.compile(path.join(__dirname, 'OfflinePage.scss'), {
      loadPaths: [SASS_ROOT],
      quietDeps: true,
      logger: sass.Logger.silent,
    })
    root = postcss.parse(css)
  })

  test('the illustration is shown above the claim', () => {
    expect(declarationsOf(root, '.offline .cat')).toMatchObject({
      height: '215px',
      background: 'url(/public/img/offline.png) no-repeat center center',
    })
  })

  test('the claim headings keep their sizes and spacing', () => {
    expect(declarationsOf(root, '.offline .claim h1')).toMatchObject({
      'font-size': '35px',
      margin: '0.67em 0',
    })
    expect(declarationsOf(root, '.offline .claim h2')).toMatchObject({
      'font-size': '30px',
      margin: '0.83em 0',
    })
  })
})
