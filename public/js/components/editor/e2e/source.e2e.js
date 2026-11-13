// The read-only source pane (M2): it cannot be edited, reports tag clicks, copies out with its tags, and shows the
// decoration layers and chip states SourceEditor drives.
import {test} from '@playwright/test'
import {
  SOURCE,
  TARGET,
  call,
  caret,
  clipboardEvent,
  events,
  expect,
  open,
  select,
  value,
} from './helpers'

test('typing, deleting and pasting leave the source unchanged', async ({
  page,
}) => {
  await open(page)
  const before = await value(page, 'source')
  await caret(page, {after: 'Click'}, 'source')
  await page.keyboard.type('xyz')
  await page.keyboard.press('Backspace')
  await page.keyboard.press('Enter')
  await clipboardEvent(page, 'paste', {'text/plain': 'pasted'}, SOURCE)
  expect(await value(page, 'source')).toBe(before)
})

test('clicking a source tag selects it and reports which tag it is', async ({
  page,
}) => {
  await open(page)
  await page.locator(`${SOURCE} .tag`).nth(2).click()
  const [click] = await events(page, 'tagClick')
  expect(click.side).toBe('source')
  expect(click.payload).toMatchObject({name: 'g', raw: '<g id="3">'})
  const selected = await page.evaluate(() => {
    const {state} = window.__editor.source.view
    const {from, to} = state.selection.main
    return state.sliceDoc(from, to)
  })
  expect(selected).toBe('<g id="3">')
})

test('a copy from the source pastes into the target with its tags', async ({
  page,
}) => {
  await open(page)
  await select(page, 'Click', 'Save', 'source')
  const copied = await clipboardEvent(page, 'copy', {}, SOURCE)
  expect(copied.plain).toBe('Click 3Save')
  await call(page, 'setValue', '', {undoable: false})
  await call(page, 'focus', 'end')
  await clipboardEvent(page, 'paste', {'text/html': copied.html}, TARGET)
  expect(await value(page)).toBe('Click <g id="3">Save')
  await expect(page.locator(`${TARGET} .tag`)).toHaveCount(1)
})

test('layer marks show over the source text; search hides the other layers', async ({
  page,
}) => {
  await open(page)
  const range = (word, className, tagName) =>
    page.evaluate(
      ([w, c, t]) => {
        const from = window.__editor.doc('source').indexOf(w)
        return {from, to: from + w.length, className: c, tagName: t}
      },
      [word, className, tagName],
    )
  const glossary = await range('Save', 'glossaryItem')
  const search = await range('Second', 'searchMarker', 'mark')
  await page.evaluate(
    (r) => window.__editor.layer('source', 'glossary', [r]),
    glossary,
  )
  await expect(page.locator(`${SOURCE} .glossaryItem`)).toHaveText('Save')
  await page.evaluate(
    (r) => window.__editor.layer('source', 'search', [r]),
    search,
  )
  await expect(page.locator(`${SOURCE} mark.searchMarker`)).toHaveText('Second')
  await expect(page.locator(`${SOURCE} .glossaryItem`)).toHaveCount(0)
  await page.evaluate(() => window.__editor.layer('source', 'search', []))
  await expect(page.locator(`${SOURCE} .glossaryItem`)).toHaveText('Save')
})

test('a chip state class (tag-clicked) shows on that chip only', async ({
  page,
}) => {
  await open(page)
  await page.evaluate(() =>
    window.__editor.tagState('source', [[2, 'tag-clicked']]),
  )
  const chips = page.locator(`${SOURCE} .tag`)
  await expect(chips.nth(2)).toHaveClass(/tag-clicked/)
  await expect(page.locator(`${SOURCE} .tag-clicked`)).toHaveCount(1)
})
