// Contract items 7, 16 and the drop part of 17 of the editor spike, against the core.
import {test} from '@playwright/test'
import {SEGMENTS} from './fixtures'
import {
  SOURCE,
  TARGET,
  caret,
  clipboardEvent,
  expect,
  open,
  replaceOnce,
  select,
  textRect,
  value,
} from './helpers'

const {source, target} = SEGMENTS.basic
const CLICK_TO_SAVE = 'Click <g id="3">Save</g>'

/** The raw slice carried in the html flavour written by a copy. */
const sliceOf = (page, html) =>
  page.evaluate((h) => {
    const m = /data-matecat-segment="([^"]*)"/.exec(h)
    return (
      m &&
      new TextDecoder().decode(
        Uint8Array.from(atob(m[1]), (c) => c.charCodeAt(0)),
      )
    )
  }, html)

test.describe('copy and paste', () => {
  test('copy from the source writes the raw slice and the plain text', async ({
    page,
  }) => {
    await open(page)
    await select(page, 'Click', 'Save</g>')
    const written = await clipboardEvent(page, 'copy', {}, SOURCE)
    expect(await sliceOf(page, written.html)).toBe(CLICK_TO_SAVE)
    // tag chips as the text they show, as a SegmentView copy writes it
    expect(written.plain).toBe('Click 3Save3')
  })

  test('a slice copied from the source pastes into the target with its tags', async ({
    page,
  }) => {
    await open(page)
    await select(page, 'Click', 'Save</g>')
    const written = await clipboardEvent(page, 'copy', {}, SOURCE)
    await caret(page, {before: 'Seconda'})
    await clipboardEvent(page, 'paste', {
      'text/html': written.html,
      'text/plain': written.plain,
    })
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', CLICK_TO_SAVE + 'Seconda'),
    )
    expect(await page.locator(`${TARGET} .tag`).count()).toBeGreaterThan(0)
  })

  test('outside text pastes without tag markup, ° as an nbsp', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await clipboardEvent(page, 'paste', {'text/plain': 'a<x id="1"/>b°c & d'})
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', 'ab##$_A0$##c &amp; dSeconda'),
    )
  })

  test('cut removes the slice and undo brings it back with its tags', async ({
    page,
  }) => {
    await open(page)
    await select(page, 'Fai', 'Salva</g>', 'target')
    const written = await clipboardEvent(page, 'cut')
    expect(await sliceOf(page, written.html)).toBe(
      'Fai clic su <g id="3">Salva</g>',
    )
    expect(await value(page)).toBe(
      replaceOnce(target, 'Fai clic su <g id="3">Salva</g>', ''),
    )
    await page.keyboard.press('ControlOrMeta+z')
    expect(await value(page)).toBe(target)
  })

  test('real keyboard copy from the source and paste into the target', async ({
    page,
    browserName,
    context,
  }) => {
    test.skip(
      browserName !== 'chromium',
      'system clipboard permissions are Chromium-only in Playwright',
    )
    await context.grantPermissions(['clipboard-read', 'clipboard-write'])
    await open(page)
    await select(page, 'Click', 'Save</g>')
    await page.keyboard.press('ControlOrMeta+c')
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('ControlOrMeta+v')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', CLICK_TO_SAVE + 'Seconda'),
    )
  })
})

/** Drops `data` at a point of the target. */
const dropAt = (page, point, data) =>
  page.locator(TARGET).evaluate(
    (el, [p, d]) => {
      const dt = new DataTransfer()
      for (const [k, v] of Object.entries(d)) dt.setData(k, v)
      const at = document.elementFromPoint(p.x, p.y)
      for (const type of ['dragover', 'drop']) {
        const event = new DragEvent(type, {
          dataTransfer: dt,
          clientX: p.x,
          clientY: p.y,
          bubbles: true,
          cancelable: true,
        })
        if (event.dataTransfer !== dt)
          Object.defineProperty(event, 'dataTransfer', {value: dt})
        at.dispatchEvent(event)
      }
    },
    [point, data],
  )

test.describe('drop', () => {
  test('a slice dropped on text is inserted with its tags', async ({page}) => {
    await open(page)
    await select(page, 'Click', 'Save</g>')
    const {html, plain} = await clipboardEvent(page, 'copy', {}, SOURCE)
    const r = await textRect(page, 'Seconda')
    await dropAt(
      page,
      {x: r.left + 1, y: (r.top + r.bottom) / 2},
      {'text/html': html, 'text/plain': plain},
    )
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', CLICK_TO_SAVE + 'Seconda'),
    )
    expect(await value(page, 'source')).toBe(source)
  })

  test('nothing may be dropped onto a chip', async ({page}) => {
    await open(page)
    const chip = await page.locator(`${TARGET} .tag`).nth(2).boundingBox()
    await dropAt(
      page,
      {x: chip.x + chip.width / 2, y: chip.y + chip.height / 2},
      {'text/plain': 'zzz'},
    )
    expect(await value(page)).toBe(target)
  })

  test('outside text dropped on text is inserted without tag markup', async ({
    page,
  }) => {
    await open(page)
    const r = await textRect(page, 'Seconda')
    await dropAt(
      page,
      {x: r.left + 1, y: (r.top + r.bottom) / 2},
      {'text/plain': '<x id="5"/>new '},
    )
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', 'new Seconda'),
    )
  })
})
