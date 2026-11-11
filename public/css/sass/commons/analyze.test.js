import path from 'path'
import * as sass from 'sass'
import postcss from 'postcss'

// The job cards at the top of the Volume analysis page are rendered with the same
// markup for every workflow type: AnalyzeChunksResume puts them in
// `.project-top.type-<workflow_type>`. The card styles used to require
// `.type-standard`, so MTQE projects got bare, unstyled cards. jsdom does no layout,
// so the contract is asserted on the compiled stylesheet: the rule that lays out a
// card has to match the card element of every workflow type.
const SASS_ROOT = path.resolve(__dirname, '..')

const buildSummaryCard = (workflowType) => {
  document.body.innerHTML = `
    <div class="project-list">
      <div class="project">
        <div class="project-top type-${workflowType}">
          <div class="project-card"></div>
        </div>
      </div>
    </div>`
  return document.querySelector('.project-card')
}

const declarationsMatching = (root, element) => {
  const declarations = {}
  root.walkRules((rule) => {
    // Nested in @media: only the base rules matter here
    if (rule.parent.type !== 'root') return
    if (!rule.selectors.some((selector) => element.matches(selector))) return
    rule.walkDecls((decl) => {
      declarations[decl.prop] = decl.value
    })
  })
  return declarations
}

describe('_analyze.scss job summary cards', () => {
  let root

  beforeAll(() => {
    const {css} = sass.compile(path.join(__dirname, '_analyze.scss'), {
      loadPaths: [SASS_ROOT],
      quietDeps: true,
      logger: sass.Logger.silent,
    })
    root = postcss.parse(css)
  })

  test.each(['standard', 'mtqe'])(
    'a %s project gets the card layout',
    (workflowType) => {
      const card = buildSummaryCard(workflowType)

      expect(declarationsMatching(root, card)).toMatchObject({
        display: 'flex',
        'flex-direction': 'column',
        'border-radius': '16px',
      })
    },
  )
})
