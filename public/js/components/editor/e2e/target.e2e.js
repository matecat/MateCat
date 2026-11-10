// The target's own editing (milestone M3a): tag menu, cattool keys and the paste mapping. IME next to the menu is
// in ime.e2e.js.
import {test} from '@playwright/test'
import {SEGMENTS} from './fixtures'
import {
  TARGET,
  call,
  caret,
  clipboardEvent,
  events,
  expect,
  open,
  replaceOnce,
  textRect,
  value,
} from './helpers'

const {target} = SEGMENTS.basic

const menuEvents = (page) => events(page, 'tagMenu')
const lastMenu = async (page) => (await menuEvents(page)).at(-1)?.payload

test.describe('tag menu', () => {
  test('typing < opens the menu under the caret; the chosen tag replaces the <', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.locator(TARGET).focus()
    await page.keyboard.type('<')
    await expect.poll(() => lastMenu(page)).not.toBeNull()
    const menu = await lastMenu(page)
    const rect = await textRect(page, 'Seconda')
    expect(menu.trigger).not.toBeNull()
    expect(Math.abs(menu.coords.top - rect.top)).toBeLessThan(5)
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', '&lt;Seconda'),
    )
    await call(page, 'insertMenuTag', '<x id="9"/>')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', '<x id="9"/>Seconda'),
    )
    expect(await lastMenu(page)).toBeNull()
    await expect(page.locator(`${TARGET} .tag`)).toHaveCount(
      (await page.locator(`[data-testid=source] .tag`).count()) + 1,
    )
  })

  test('Alt+T opens the menu without typing; the arrows and Enter go to the menu, not the text', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('Alt+t')
    await expect.poll(() => lastMenu(page)).toMatchObject({trigger: null})
    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('ArrowUp')
    await page.keyboard.press('Enter')
    const keys = (await events(page, 'tagMenuKey')).map((e) => e.payload)
    expect(keys).toEqual(['down', 'up', 'enter'])
    expect(await value(page)).toBe(target)
  })

  test('Escape closes the menu and leaves the < as text', async ({page}) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.keyboard.type('<')
    await expect.poll(() => lastMenu(page)).not.toBeNull()
    await page.keyboard.press('Escape')
    await expect.poll(() => lastMenu(page)).toBeNull()
    await page.keyboard.type('b')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', '&lt;bSeconda'),
    )
  })

  test('moving the caret closes the menu', async ({page}) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('Alt+t')
    await expect.poll(() => lastMenu(page)).not.toBeNull()
    await page.keyboard.press('ArrowLeft')
    await expect.poll(() => lastMenu(page)).toBeNull()
  })

  test('with no tags to offer, < is plain text and no menu opens', async ({
    page,
  }) => {
    await open(page, 'basic', '&notags')
    await caret(page, {before: 'Seconda'})
    await page.keyboard.type('<')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', '&lt;Seconda'),
    )
    expect(await menuEvents(page)).toEqual([])
  })
})

test.describe('cattool keys', () => {
  test('Ctrl+Alt+Space inserts a word joiner, shown as a mark and not as a tag', async ({
    page,
  }) => {
    await open(page)
    const chips = await page.locator(`${TARGET} .tag`).count()
    const marks = await page.locator(`${TARGET} .cm-word-joiner`).count()
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('Control+Alt+Space')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', '\u2060Seconda'),
    )
    await expect(page.locator(`${TARGET} .cm-word-joiner`)).toHaveCount(
      marks + 1,
    )
    await expect(page.locator(`${TARGET} .tag`)).toHaveCount(chips)
  })

  test('Ctrl+[ and Ctrl+] type the single smart quotes, with Shift the double ones', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('Control+BracketLeft')
    await page.keyboard.press('Control+BracketRight')
    await page.keyboard.press('Control+Shift+BracketLeft')
    await page.keyboard.press('Control+Shift+BracketRight')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', '‘’“”Seconda'),
    )
  })

  test('add issue (Ctrl+Alt+Enter) and Ctrl+K reach the page and change nothing', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('Control+Alt+Enter')
    await page.keyboard.press('Control+k')
    const keys = await events(page, 'hostKey')
    expect(keys.map((e) => [e.payload, e.defaultPrevented])).toEqual([
      ['Ctrl-Alt-Enter', true],
      ['Ctrl-k', true],
    ])
    expect(await value(page)).toBe(target)
  })
})

test.describe('paste mapping', () => {
  test('outside text keeps its tabs as tabs and a degree sign as a degree sign', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    const nbspMarks = await page.locator(`${TARGET} .cm-nbsp`).count()
    await clipboardEvent(page, 'paste', {'text/plain': 'a\tb 25°C'})
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', 'a##$_09$##b 25°CSeconda'),
    )
    await expect(page.locator(`${TARGET} .cm-nbsp`)).toHaveCount(nbspMarks)
  })
})

test.describe('special characters', () => {
  const MARKS = [
    ['.cm-typographic-space', 'Em space (U+2003)'],
    ['.cm-separator-mark', 'Line separator (U+2028)'],
    ['.cm-zwsp', 'Zero-width space (U+200B)'],
  ]

  test('em space, line separator and zero-width space each show their own visible mark', async ({
    page,
  }) => {
    await open(page, 'special')
    const marks = []
    for (const [selector, title] of MARKS) {
      const mark = page.locator(`${TARGET} ${selector}`)
      await expect(mark).toHaveCount(1)
      await expect(mark).toHaveAttribute('title', title)
      // drawn by the editor, not by a font glyph that may be missing (LSEP in Chrome on macOS)
      const box = await mark.boundingBox()
      expect(box.width).toBeGreaterThan(1)
      expect(box.height).toBeGreaterThan(1)
      marks.push(
        await mark.evaluate((el) => {
          const own = getComputedStyle(el)
          const after = getComputedStyle(el, '::after')
          return [
            own.backgroundImage,
            own.borderTopStyle,
            after.content,
            el.textContent,
          ].join('|')
        }),
      )
    }
    expect(new Set(marks).size).toBe(MARKS.length)
    expect(await value(page)).toBe(SEGMENTS.special.target)
  })

  test('the em space mark is as wide as an em space in plain text', async ({
    page,
  }) => {
    await open(page, 'special')
    const {mark, plain} = await page
      .locator(`${TARGET} .cm-typographic-space`)
      .evaluate((el) => {
        const ctx = document.createElement('canvas').getContext('2d')
        ctx.font = getComputedStyle(el).font
        return {
          mark: el.getBoundingClientRect().width,
          plain: ctx.measureText('\u2003').width,
        }
      })
    expect(Math.abs(mark - plain)).toBeLessThan(0.5)
  })
})
