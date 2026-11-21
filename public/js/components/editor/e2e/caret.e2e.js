// Caret movement next to tags, across lines and in right-to-left text: what Draft gave translators through the
// browser's own caret, which the CodeMirror target has to keep.
import {test} from '@playwright/test'
import {SEGMENTS} from './fixtures'
import {caret, expect, open, replaceOnce, value} from './helpers'

const {target} = SEGMENTS.basic
const {target: rtl} = SEGMENTS.rtl

/** The target selection as document positions, with the line of its head. */
const selection = (page) =>
  page.evaluate(() => {
    const {state} = window.__editor.target.view
    const {anchor, head} = state.selection.main
    return {anchor, head, line: state.doc.lineAt(head).number}
  })

/** Word moves are Alt+arrow on a Mac and Ctrl+arrow elsewhere. */
const wordKey = async (page) =>
  (await page.evaluate(() => /Mac/.test(navigator.platform)))
    ? 'Alt'
    : 'Control'

/** Fails when the target caret sits strictly inside a chip. */
const expectOutsideChips = async (page) => {
  const {head} = await selection(page)
  const chips = await page.evaluate(() => window.__editor.chipRanges('target'))
  expect(chips.length).toBeGreaterThan(0)
  expect(chips.every(({to, from}) => to > from)).toBe(true)
  const inside = chips.filter(({from, to}) => head > from && head < to)
  expect(inside, `caret ${head} is inside a chip`).toEqual([])
}

/** The segment string with everything from the start of `from` up to the start of `to` removed. */
const without = (raw, from, to) =>
  raw.slice(0, raw.indexOf(from)) + raw.slice(raw.indexOf(to))

test.describe('selecting across tags', () => {
  test('Shift+ArrowRight takes a whole tag in one step', async ({page}) => {
    await open(page)
    await caret(page, {after: 'su '})
    await page.keyboard.press('Shift+ArrowRight')
    await page.keyboard.press('Shift+ArrowRight')
    await page.keyboard.press('Backspace')
    expect(await value(page)).toBe(replaceOnce(target, '<g id="3">S', ''))
  })

  test('Shift+ArrowLeft takes a whole tag in one step', async ({page}) => {
    await open(page)
    await caret(page, {before: 'per'})
    // back over the space, the closing tag and the last letter of "Salva"
    await page.keyboard.press('Shift+ArrowLeft')
    await page.keyboard.press('Shift+ArrowLeft')
    await page.keyboard.press('Shift+ArrowLeft')
    await page.keyboard.press('Backspace')
    expect(await value(page)).toBe(replaceOnce(target, 'a</g> ', ''))
  })

  test('Shift+ArrowLeft after Shift+ArrowRight gives the tag back', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {after: 'su '})
    const start = await selection(page)
    await page.keyboard.press('Shift+ArrowRight')
    await page.keyboard.press('Shift+ArrowLeft')
    expect(await selection(page)).toEqual(start)
  })

  test('Shift+End selects to the end of the line, tags included', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Fai'})
    await page.keyboard.press('Shift+End')
    await page.keyboard.press('Backspace')
    expect(await value(page)).toBe(without(target, 'Fai', '##$_0A$##'))
  })

  test('Shift+Home selects to the start of the line, tags included', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {after: 'Fai'})
    await page.keyboard.press('Shift+Home')
    await page.keyboard.press('Backspace')
    expect(await value(page)).toBe(target.slice(target.indexOf(' clic')))
  })
})

test.describe('Home and End', () => {
  test('End goes after the tags that close the line, before the line break', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Fai'})
    await page.keyboard.press('End')
    await page.keyboard.type('E')
    expect(await value(page)).toBe(
      replaceOnce(target, '##$_0A$##', 'E##$_0A$##'),
    )
  })

  test('Home goes before the tags that open the line', async ({page}) => {
    await open(page)
    await caret(page, {after: 'clic'})
    await page.keyboard.press('Home')
    await page.keyboard.type('Q')
    expect(await value(page)).toBe('Q' + target)
  })

  test('Home on the second line goes to its start, not the segment start', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {after: 'riga'})
    await page.keyboard.press('Home')
    await page.keyboard.type('Q')
    expect(await value(page)).toBe(replaceOnce(target, 'Seconda', 'QSeconda'))
  })

  test('End on the last line goes after the trailing text', async ({page}) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('End')
    await page.keyboard.type('E')
    expect(await value(page)).toBe(target + 'E')
  })
})

test.describe('word moves next to tags', () => {
  test('a word move forward stops at the end of the word after a tag', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Salva'})
    await page.keyboard.press(`${await wordKey(page)}+ArrowRight`)
    await page.keyboard.type('Z')
    expect(await value(page)).toBe(replaceOnce(target, 'Salva', 'SalvaZ'))
  })

  test('a word move back stops at the start of the word before a tag', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {after: 'Salva'})
    await page.keyboard.press(`${await wordKey(page)}+ArrowLeft`)
    await page.keyboard.type('Z')
    expect(await value(page)).toBe(replaceOnce(target, 'Salva', 'ZSalva'))
  })

  test('word moves never stop inside a tag', async ({page}) => {
    await open(page)
    const key = await wordKey(page)
    // forward from the segment start to its end, then back, over every chip of both lines
    await caret(page, {before: 'Fai'})
    await page.keyboard.press('Home')
    let last = -1
    for (let i = 0; i < 30 && (await selection(page)).head !== last; i++) {
      last = (await selection(page)).head
      await page.keyboard.press(`${key}+ArrowRight`)
      await expectOutsideChips(page)
    }
    last = -1
    for (let i = 0; i < 30 && (await selection(page)).head !== last; i++) {
      last = (await selection(page)).head
      await page.keyboard.press(`${key}+ArrowLeft`)
      await expectOutsideChips(page)
    }
    expect((await selection(page)).head).toBe(0)
  })

  test('a word delete back next to a tag leaves the tag in place', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {after: 'Salva'})
    await page.keyboard.press(`${await wordKey(page)}+Backspace`)
    expect(await value(page)).toBe(replaceOnce(target, 'Salva', ''))
  })
})

test.describe('up and down', () => {
  test('ArrowDown from the first line lands on the second, ArrowUp comes back', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Fai'})
    await page.keyboard.press('ArrowDown')
    expect((await selection(page)).line).toBe(2)
    await page.keyboard.press('ArrowUp')
    expect((await selection(page)).line).toBe(1)
  })

  test('ArrowDown on the last line goes to its end', async ({page}) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('ArrowDown')
    await page.keyboard.type('E')
    expect(await value(page)).toBe(target + 'E')
  })

  test('ArrowUp on the first line goes to the segment start', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {after: 'clic'})
    await page.keyboard.press('ArrowUp')
    await page.keyboard.type('Q')
    expect(await value(page)).toBe('Q' + target)
  })

  test('in a wrapped line ArrowDown moves one row and stays in the same line', async ({
    page,
  }) => {
    await open(page)
    await page.addStyleTag({content: '[data-testid=target] {width: 140px}'})
    await caret(page, {before: 'Fai'})
    const before = await selection(page)
    await page.keyboard.press('ArrowDown')
    const after = await selection(page)
    expect(after.line).toBe(1)
    expect(after.head).toBeGreaterThan(before.head)
    // and back up to the row it came from
    await page.keyboard.press('ArrowUp')
    expect((await selection(page)).head).toBeLessThan(after.head)
    expect((await selection(page)).line).toBe(1)
  })

  test('ArrowDown across a wrapped line never stops inside a tag', async ({
    page,
  }) => {
    await open(page)
    await page.addStyleTag({content: '[data-testid=target] {width: 140px}'})
    await caret(page, {before: 'Fai'})
    for (let i = 0; i < 8; i++) {
      await page.keyboard.press('ArrowDown')
      await expectOutsideChips(page)
    }
    for (let i = 0; i < 8; i++) {
      await page.keyboard.press('ArrowUp')
      await expectOutsideChips(page)
    }
  })
})

test.describe('right-to-left target', () => {
  // In a right-to-left line the arrows follow the screen: ArrowLeft moves forward in the text.
  test('ArrowLeft moves forward and ArrowRight back', async ({page}) => {
    await open(page, 'rtl')
    await caret(page, {after: 'اضغط'})
    const start = (await selection(page)).head
    await page.keyboard.press('ArrowLeft')
    expect((await selection(page)).head).toBe(start + 1)
    await page.keyboard.press('ArrowRight')
    await page.keyboard.press('ArrowRight')
    expect((await selection(page)).head).toBe(start - 1)
  })

  test('one ArrowLeft crosses a tag forward', async ({page}) => {
    await open(page, 'rtl')
    await caret(page, {after: 'على '})
    await page.keyboard.press('ArrowLeft')
    await page.keyboard.type('Z')
    expect(await value(page)).toBe(
      replaceOnce(rtl, '<g id="1">', '<g id="1">Z'),
    )
  })

  test('one ArrowRight crosses a tag back', async ({page}) => {
    await open(page, 'rtl')
    await caret(page, {before: 'موافق'})
    await page.keyboard.press('ArrowRight')
    await page.keyboard.type('Z')
    expect(await value(page)).toBe(
      replaceOnce(rtl, '<g id="1">', 'Z<g id="1">'),
    )
  })

  test('Shift+ArrowLeft selects a tag and the letter after it', async ({
    page,
  }) => {
    await open(page, 'rtl')
    await caret(page, {after: 'على '})
    await page.keyboard.press('Shift+ArrowLeft')
    await page.keyboard.press('Shift+ArrowLeft')
    await page.keyboard.press('Backspace')
    expect(await value(page)).toBe(replaceOnce(rtl, '<g id="1">م', ''))
  })
})
