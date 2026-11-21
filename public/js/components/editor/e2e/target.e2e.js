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
  select,
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
    await expect.poll(() => lastMenu(page)).toBeTruthy()
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
    await expect.poll(() => lastMenu(page)).toBeTruthy()
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
    await expect.poll(() => lastMenu(page)).toBeTruthy()
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
    const marks = await page
      .locator(`${TARGET} .special-char-word-joiner`)
      .count()
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('Control+Alt+Space')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', '\u2060Seconda'),
    )
    await expect(
      page.locator(`${TARGET} .special-char-word-joiner`),
    ).toHaveCount(marks + 1)
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
    const nbspMarks = await page.locator(`${TARGET} .special-char-nbsp`).count()
    await clipboardEvent(page, 'paste', {'text/plain': 'a\tb 25°C'})
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', 'a##$_09$##b 25°CSeconda'),
    )
    await expect(page.locator(`${TARGET} .special-char-nbsp`)).toHaveCount(
      nbspMarks,
    )
  })
})

test.describe('special characters', () => {
  const MARKS = [
    ['.special-char-typographic-space', 'Em space (U+2003)'],
    ['.special-char-mark-separator', 'Line separator (U+2028)'],
    ['.special-char-zwsp', 'Zero-width space (U+200B)'],
  ]

  test('em space, line separator and zero-width space each show their own visible mark', async ({
    page,
  }) => {
    await open(page, 'special')
    const marks = []
    for (const [selector, title] of MARKS) {
      const mark = page.locator(`${TARGET} ${selector}`)
      await expect(mark).toHaveCount(1)
      await expect(mark).toHaveAttribute('aria-label', title)
      await expect(mark).not.toHaveAttribute('title')
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
      .locator(`${TARGET} .special-char-typographic-space`)
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

/** The cattool tooltip of an element (its `::after`, see commons/_aria-label-tooltip.scss): text and opacity. */
const tooltip = (locator) =>
  locator.evaluate((el) => {
    const after = getComputedStyle(el, '::after')
    return {text: after.content, opacity: after.opacity}
  })

/** Moves the mouse over the middle of an element (a space mark is a few pixels wide: no actionability wait). */
const hover = async (page, locator) => {
  const box = await locator.boundingBox()
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2)
}

// The harness loads the cattool tooltip stylesheet: it owns ::before/::after of every [aria-label] element, so a mark
// that drew itself with them was hidden (opacity 0 until hover) or lost its tooltip.
test.describe('marks under the cattool tooltip', () => {
  test('the nbsp ° shows without hover, and hovering the mark shows its name', async ({
    page,
  }) => {
    await open(page)
    await page.mouse.move(0, 0)
    const mark = page.locator(`${TARGET} .special-char-nbsp`)
    const label = mark.locator('.special-char-mark-label')
    const shown = await label.evaluate((el) => {
      const before = getComputedStyle(el, '::before')
      return {content: before.content, opacity: before.opacity}
    })
    expect(shown).toEqual({content: '"°"', opacity: '1'})
    const box = await label.boundingBox()
    expect(box.width).toBeGreaterThan(0)
    expect(box.height).toBeGreaterThan(0)
    expect(await tooltip(mark)).toEqual({
      text: '"Non-breaking space (U+00A0)"',
      opacity: '0',
    })

    await hover(page, mark)
    await expect
      .poll(() => tooltip(mark))
      .toEqual({text: '"Non-breaking space (U+00A0)"', opacity: '1'})
  })

  test('nothing inside the editor clips the tooltip of a mark on the first line', async ({
    page,
  }) => {
    await open(page)
    const clipping = await page
      .locator(`${TARGET} .special-char-nbsp`)
      .first()
      .evaluate((mark) => {
        const found = []
        for (let el = mark.parentElement; el; el = el.parentElement) {
          const {overflowX, overflowY} = getComputedStyle(el)
          if ([overflowX, overflowY].some((o) => o !== 'visible'))
            found.push(el.className)
          if (el.classList.contains('cm-editor')) break
        }
        return found
      })
    expect(clipping).toEqual([])
  })

  test('every special character mark shows its own name on hover', async ({
    page,
  }) => {
    await open(page, 'special')
    for (const [selector, title] of [
      ['.special-char-typographic-space', 'Em space (U+2003)'],
      ['.special-char-mark-separator', 'Line separator (U+2028)'],
      ['.special-char-zwsp', 'Zero-width space (U+200B)'],
    ]) {
      const mark = page.locator(`${TARGET} ${selector}`)
      await hover(page, mark)
      await expect
        .poll(() => tooltip(mark))
        .toEqual({text: `"${title}"`, opacity: '1'})
    }
    // the bracket of a space is still drawn, by its own element
    const bracket = await page
      .locator(
        `${TARGET} .special-char-typographic-space .special-char-mark-bracket`,
      )
      .boundingBox()
    expect(bracket.width).toBeGreaterThan(1)
    expect(bracket.height).toBeGreaterThan(1)
  })
})

test.describe('line breaks', () => {
  const LINE_BREAK = `${TARGET} .special-char-mark-line-break`
  const head = (page) =>
    page.evaluate(() => window.__editor.target.view.state.selection.main.head)

  test('the line that ends with a line break shows the cattool line feed glyph after its text, with a tooltip', async ({
    page,
  }) => {
    await open(page)
    const mark = page.locator(LINE_BREAK)
    await expect(mark).toHaveCount(1)
    const {glyph, color} = await mark
      .locator('.special-char-mark-label')
      .evaluate((el) => {
        const before = getComputedStyle(el, '::before')
        return {glyph: before.content, color: before.color}
      })
    expect(glyph).toBe('"↵"')
    expect(color).toBe('rgb(0, 153, 204)')
    // at the end of the first line, after its last character
    const box = await mark.boundingBox()
    const text = await textRect(page, 'modifiche.')
    expect(box.width).toBeGreaterThan(1)
    expect(box.x).toBeGreaterThan(text.right)
    expect(box.y).toBeLessThan(text.bottom)
    expect((await textRect(page, 'Seconda')).top).toBeGreaterThanOrEqual(
      text.bottom - 1,
    )

    await hover(page, mark)
    await expect
      .poll(() => tooltip(mark))
      .toEqual({text: '"Line break"', opacity: '1'})
  })

  test('the caret steps over a line break in one key press, and a copy leaves the glyph out', async ({
    page,
  }) => {
    await open(page)
    const lineEnd = await page.evaluate(
      () => window.__editor.target.view.state.doc.line(1).to,
    )
    await page.evaluate((at) => window.__editor.caret('target', at), lineEnd)
    await page.keyboard.press('ArrowRight')
    expect(await head(page)).toBe(lineEnd + 1)
    await page.keyboard.press('ArrowLeft')
    expect(await head(page)).toBe(lineEnd)

    await select(page, 'modifiche.', 'Seconda', 'target')
    const {plain} = await clipboardEvent(page, 'copy')
    expect(plain).not.toContain('↵')
    expect(plain).toContain('\n')
    expect(await value(page)).toBe(target)
  })

  test('a line break inserted in the document gets its mark, and loses it when deleted', async ({
    page,
  }) => {
    await open(page)
    const at = await page.evaluate(() =>
      window.__editor.doc('target').indexOf('qui.'),
    )
    await page.evaluate((pos) => {
      const {view} = window.__editor.target
      view.dispatch({changes: {from: pos, insert: '\n'}})
    }, at)
    await expect(page.locator(LINE_BREAK)).toHaveCount(2)
    await page.evaluate((pos) => window.__editor.caret('target', pos + 1), at)
    await page.keyboard.press('Backspace')
    await expect(page.locator(LINE_BREAK)).toHaveCount(1)
    expect(await value(page)).toBe(target)
  })

  test('in an rtl target the mark sits at the left end of its line', async ({
    page,
  }) => {
    await open(page, 'rtl')
    await call(page, 'setValue', 'اضغط##$_0A$##على')
    const box = await page.locator(LINE_BREAK).boundingBox()
    const text = await textRect(page, 'اضغط')
    expect(box.x + box.width).toBeLessThanOrEqual(text.left + 0.5)
    expect(box.y).toBeLessThan(text.bottom)
  })
})

test.describe('tab mark', () => {
  test('the tab arrow is drawn in the same blue as the other marks', async ({
    page,
  }) => {
    await open(page)
    await call(page, 'setValue', 'a##$_09$##b##$_A0$##c')
    const colors = await page.evaluate((target) => {
      const root = document.querySelector(target)
      const tab = getComputedStyle(root.querySelector('.cm-tab'), '::before')
      const nbsp = getComputedStyle(
        root.querySelector('.special-char-nbsp .special-char-mark-label'),
        '::before',
      )
      return {tab: tab.color, nbsp: nbsp.color, opacity: tab.opacity}
    }, TARGET)
    expect(colors.tab).toBe(colors.nbsp)
    expect(colors.opacity).toBe('1')
  })
})
