// The layers of the target editor: the hoverable Lexiqa tooltip with Replace and Ignore (contract items 4, 5, 22),
// search exclusivity, as the Matecat target uses them (matecat/TargetEditor.js, matecat/layerTooltip.js).
import {test} from '@playwright/test'
import {TARGET, events, expect, open, value} from './helpers'

const MULTILINE = 'tow one##$_0A$##line tow<x id="1"/> end'

/** Loads `raw` in the target and marks the `n`-th `word` (0 based) as a Lexiqa warning with suggestions. */
async function warn(page, raw, word, n, data) {
  await page.evaluate(
    ([r, w, i, d]) => {
      const {target} = window.__editor
      target.setValue(r, {undoable: false})
      const doc = target.view.state.doc.toString()
      let from = -1
      for (let k = 0; k <= i; k++) from = doc.indexOf(w, from + 1)
      window.__editor.layer('target', 'lexiqa', [
        {from, to: from + w.length, className: 'lexiqahighlight', data: d},
      ])
    },
    [raw, word, n, data],
  )
}

const mark = (page) => page.locator(`${TARGET} [data-layer=lexiqa]`)
const tooltip = (page) => page.locator('.harness-tooltip')

async function hoverMark(page) {
  const box = await mark(page).boundingBox()
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2)
  await expect(tooltip(page)).toBeVisible()
  return box
}

test('the pointer can move from the warning into its tooltip, which stays open, and use it', async ({
  page,
}) => {
  await open(page)
  await warn(page, MULTILINE, 'tow', 1, {id: 'w1', suggestions: ['two']})
  await hoverMark(page)
  const button = await page.locator('.harness-suggestion').boundingBox()
  // A normal path: from the word, across the gap, onto the button
  await page.mouse.move(
    button.x + button.width / 2,
    button.y + button.height / 2,
    {
      steps: 12,
    },
  )
  await page.waitForTimeout(600)
  await expect(tooltip(page)).toBeVisible()
  await page.locator('.harness-suggestion').click()
  // The warned word of the second line is replaced, not the one at the start (contract item 26 of the api)
  expect(await value(page)).toBe('tow one##$_0A$##line two<x id="1"/> end')
  await expect(tooltip(page)).toHaveCount(0)
  expect((await events(page, 'tooltipAction')).at(-1).payload).toEqual({
    id: 'w1',
    text: 'two',
  })
})

test('the tooltip closes a moment after the pointer has left the warning and the tooltip', async ({
  page,
}) => {
  await open(page)
  await warn(page, MULTILINE, 'tow', 0, {id: 'w1', suggestions: ['two']})
  const box = await hoverMark(page)
  await page.mouse.move(box.x + box.width / 2, box.y + 200)
  await page.waitForTimeout(100)
  await expect(tooltip(page)).toBeVisible()
  await expect(tooltip(page)).toHaveCount(0, {timeout: 1000})
})

test('Ignore drops that warning only, and is no edit', async ({page}) => {
  await open(page)
  await page.evaluate((raw) => {
    const {target} = window.__editor
    target.setValue(raw, {undoable: false})
    const doc = target.view.state.doc.toString()
    const at = (w, from = 0) => doc.indexOf(w, from)
    const second = at('tow', 1)
    window.__editor.layer('target', 'lexiqa', [
      {
        from: 0,
        to: 3,
        className: 'lexiqahighlight',
        data: {id: 'a', suggestions: []},
      },
      {
        from: second,
        to: second + 3,
        className: 'lexiqahighlight',
        data: {id: 'b', suggestions: []},
      },
    ])
    window.__editor.layer('target', 'blacklist', [
      {from: at('line'), to: at('line') + 4, className: 'blacklistItem'},
    ])
  }, MULTILINE)
  const first = await mark(page).first().boundingBox()
  await page.mouse.move(first.x + first.width / 2, first.y + first.height / 2)
  await expect(tooltip(page)).toBeVisible()
  await page.locator('.harness-ignore').click()

  await expect(mark(page)).toHaveCount(1)
  await expect(mark(page)).toHaveText('tow')
  await expect(page.locator(`${TARGET} .blacklistItem`)).toHaveCount(1)
  expect(await value(page)).toBe(MULTILINE)
  await page.evaluate(() => window.__editor.target.undo())
  expect(await value(page)).toBe(MULTILINE)
  expect((await events(page, 'tooltipAction')).at(-1).payload).toEqual({
    id: 'a',
    action: 'ignore',
  })
})

test('search in the target hides the other layers while it has hits; Replace still works', async ({
  page,
}) => {
  await open(page)
  await warn(page, 'casa tow casa', 'tow', 0, {id: 'w', suggestions: []})
  await page.evaluate(() => {
    const doc = window.__editor.doc('target')
    window.__editor.layer('target', 'search', [
      {from: 0, to: 4, className: 'searchMarker', tagName: 'mark'},
      {
        from: doc.lastIndexOf('casa'),
        to: doc.length,
        className: 'searchMarker currSearchItem',
        tagName: 'mark',
      },
    ])
  })
  await expect(page.locator(`${TARGET} mark.searchMarker`)).toHaveCount(2)
  await expect(mark(page)).toHaveCount(0)

  await page.evaluate(() =>
    window.__editor.target.replaceOccurrence(/casa/gi, 1, 'home'),
  )
  expect(await value(page)).toBe('casa tow home')

  await page.evaluate(() => window.__editor.layer('target', 'search', []))
  await expect(mark(page)).toHaveText('tow')
})
