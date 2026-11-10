// Contract items 1-3, 6, 8 (keys), 11-12, 19-21 of the editor spike, against the core.
import {test} from '@playwright/test'
import {SEGMENTS, TM_MATCH} from './fixtures'
import {
  SOURCE,
  TARGET,
  call,
  caret,
  chipTexts,
  events,
  expect,
  expectedChipTexts,
  open,
  replaceOnce,
  textRect,
  value,
} from './helpers'

const {target} = SEGMENTS.basic

test.describe('tags render as cattool chips', () => {
  for (const [seg, fixture] of Object.entries(SEGMENTS)) {
    test(`${seg}: one chip per tag, in order, with the cattool label`, async ({
      page,
    }) => {
      await open(page, seg)
      expect(await chipTexts(page, TARGET)).toEqual(
        await expectedChipTexts(page, fixture.target),
      )
      expect(await chipTexts(page, SOURCE)).toEqual(
        await expectedChipTexts(page, fixture.source),
      )
    })
  }

  test('chips are not editable and carry the open/close shapes', async ({
    page,
  }) => {
    await open(page)
    const first = page.locator(`${TARGET} .tag`).first()
    await expect(first).toHaveAttribute('contenteditable', 'false')
    await expect(first).toHaveClass(/tag-open/)
  })

  test('in a right-to-left target the open and close shapes swap', async ({
    page,
  }) => {
    await open(page, 'rtl')
    await expect(page.locator(`${TARGET} .tag`).first()).toHaveClass(
      /tag-close/,
    )
  })
})

test.describe('round trip', () => {
  for (const [seg, fixture] of Object.entries(SEGMENTS)) {
    test(`${seg}: target and source read back byte for byte`, async ({
      page,
    }) => {
      await open(page, seg)
      expect(await value(page)).toBe(fixture.target)
      expect(await value(page, 'source')).toBe(fixture.source)
    })
  }

  test('typing a character and deleting it restores the exact string', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {after: 'Seconda'})
    await page.keyboard.type('x')
    await page.keyboard.press('Backspace')
    expect(await value(page)).toBe(target)
  })

  test('the last change event carries the current string', async ({page}) => {
    await open(page)
    await caret(page, {after: 'clic'})
    await page.keyboard.type('k')
    const changes = await events(page, 'change')
    expect(changes.at(-1).payload).toBe(await value(page))
  })
})

test.describe('caret and deletion next to tags', () => {
  test('typing right after a tag goes after its markup', async ({page}) => {
    await open(page)
    await caret(page, {before: 'Salva'})
    await page.keyboard.type('Z')
    expect(await value(page)).toBe(replaceOnce(target, 'Salva', 'ZSalva'))
  })

  test('typing right before a tag goes before its markup', async ({page}) => {
    await open(page)
    await caret(page, {after: 'Salva'})
    await page.keyboard.type('Z')
    expect(await value(page)).toBe(replaceOnce(target, 'Salva', 'SalvaZ'))
  })

  test('one ArrowRight crosses a tag; one ArrowLeft crosses it back', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {after: 'su '})
    await page.keyboard.press('ArrowRight')
    await page.keyboard.type('Z')
    expect(await value(page)).toBe(replaceOnce(target, 'Salva', 'ZSalva'))
    await page.keyboard.press('ArrowLeft')
    await page.keyboard.press('ArrowLeft')
    await page.keyboard.type('Y')
    expect(await value(page)).toBe(
      replaceOnce(target, 'su <g id="3">Salva', 'su Y<g id="3">ZSalva'),
    )
  })

  test('backspace after a tag removes exactly that tag', async ({page}) => {
    await open(page)
    await caret(page, {before: 'Salva'})
    await page.keyboard.press('Backspace')
    expect(await value(page)).toBe(replaceOnce(target, '<g id="3">', ''))
  })

  test('delete before a tag removes exactly that tag', async ({page}) => {
    await open(page)
    await caret(page, {after: 'Salva'})
    await page.keyboard.press('Delete')
    expect(await value(page)).toBe(replaceOnce(target, 'Salva</g>', 'Salva'))
  })

  test('typing at the very start goes before back-to-back opening tags', async ({
    page,
  }) => {
    await open(page)
    await call(page, 'focus', 'start')
    await page.keyboard.type('Q')
    expect(await value(page)).toBe('Q' + target)
  })

  test('one ArrowRight from the start lands between back-to-back tags', async ({
    page,
  }) => {
    await open(page)
    await call(page, 'focus', 'start')
    await page.keyboard.press('ArrowRight')
    await page.keyboard.type('Q')
    expect(await value(page)).toBe(
      replaceOnce(target, '<g id="1"><x', '<g id="1">Q<x'),
    )
  })

  test('ArrowLeft from the start of line 2 lands at the end of line 1', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('ArrowLeft')
    await page.keyboard.type('E')
    expect(await value(page)).toBe(
      replaceOnce(target, '##$_0A$##', 'E##$_0A$##'),
    )
  })

  test('in right-to-left text backspace after a tag still removes that tag', async ({
    page,
  }) => {
    await open(page, 'rtl')
    const {target: rtl} = SEGMENTS.rtl
    const close = rtl.indexOf('</g>')
    await page.evaluate(
      (at) => window.__editor.caret('target', at),
      close + '</g>'.length,
    )
    await page.keyboard.press('Backspace')
    expect(await value(page)).toBe(replaceOnce(rtl, '</g>', ''))
  })

  test('clicking a chip selects exactly that tag and reports tagClick', async ({
    page,
  }) => {
    await open(page)
    await page.locator(`${TARGET} .tag`).nth(1).click()
    expect((await call(page, 'getSelection')).textWithTags).toBe('<x id="2"/>')
    const clicks = await events(page, 'tagClick')
    expect(clicks.at(-1)).toMatchObject({
      side: 'target',
      payload: {name: 'x', raw: '<x id="2"/>'},
    })
  })
})

test.describe('text, entities and typed markup', () => {
  test('an entity shows decoded and one Backspace deletes it', async ({
    page,
  }) => {
    await open(page, 'ph')
    const {target: ph} = SEGMENTS.ph
    await caret(page, {after: ' &'})
    await page.keyboard.press('Backspace')
    expect(await value(page)).toBe(replaceOnce(ph, ' &amp; ', '  '))
  })

  test('typed &, < and > are stored encoded', async ({page}) => {
    await open(page)
    await caret(page, {after: 'Seconda'})
    await page.keyboard.type(' a&b<c>')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', 'Seconda a&amp;b&lt;c&gt;'),
    )
  })

  test('typed tag markup stays text and makes no chip', async ({page}) => {
    await open(page)
    const chips = await chipTexts(page)
    await caret(page, {after: 'Seconda'})
    await page.keyboard.type('<x id="9"/>')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', 'Seconda&lt;x id="9"/&gt;'),
    )
    expect(await chipTexts(page)).toEqual(chips)
  })

  test('a ph chip shows its equiv-text, never its markup', async ({page}) => {
    await open(page, 'ph')
    const texts = await chipTexts(page)
    expect(texts).toContain('<br/>')
    expect(texts.join('')).not.toContain('equiv-text')
  })
})

test.describe('line feeds and Enter', () => {
  test('text after a line feed renders on a new line', async ({page}) => {
    await open(page)
    const before = await textRect(page, 'modifiche.')
    const after = await textRect(page, 'Seconda')
    expect(after.top).toBeGreaterThanOrEqual(before.bottom - 1)
    expect(after.left).toBeLessThan(before.left)
  })

  test('Enter inserts a line feed at the caret', async ({page}) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('Enter')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', '##$_0A$##Seconda'),
    )
  })

  test('Ctrl/Cmd+Enter is left to the page and changes nothing', async ({
    page,
  }) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('ControlOrMeta+Enter')
    expect(await value(page)).toBe(target)
    expect(await events(page, 'hostKey')).toHaveLength(1)
  })
})

test.describe('keys', () => {
  test('Tab inserts a tab', async ({page}) => {
    await open(page)
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('Tab')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', '##$_09$##Seconda'),
    )
  })

  test('Ctrl/Cmd+Shift+Space inserts an nbsp, shown as a marked character', async ({
    page,
  }) => {
    await open(page)
    const marks = await page.locator(`${TARGET} .cm-nbsp`).count()
    await caret(page, {before: 'Seconda'})
    await page.keyboard.press('ControlOrMeta+Shift+Space')
    expect(await value(page)).toBe(
      replaceOnce(target, 'Seconda', '##$_A0$##Seconda'),
    )
    await expect(page.locator(`${TARGET} .cm-nbsp`)).toHaveCount(marks + 1)
  })

  test('the read-only source refuses typing', async ({page}) => {
    await open(page)
    await caret(page, {before: 'Click'}, 'source')
    await page.keyboard.type('Z')
    await page.keyboard.press('Enter')
    expect(await value(page, 'source')).toBe(SEGMENTS.basic.source)
  })

  test('spaces are marked only with the whitespace option, and stay text', async ({
    page,
  }) => {
    await open(page)
    await expect(page.locator(`${TARGET} .cm-highlightSpace`)).toHaveCount(0)
    await open(page, 'basic', '&whitespace')
    expect(
      await page.locator(`${TARGET} .cm-highlightSpace`).count(),
    ).toBeGreaterThan(0)
    expect(await value(page)).toBe(target)
  })
})

test.describe('undo', () => {
  test('setValue is one undo step; redo applies it again', async ({page}) => {
    await open(page)
    await call(page, 'setValue', TM_MATCH)
    expect(await value(page)).toBe(TM_MATCH)
    await call(page, 'undo')
    expect(await value(page)).toBe(target)
    await call(page, 'redo')
    expect(await value(page)).toBe(TM_MATCH)
  })

  test('Ctrl/Cmd+Z after typing restores the string', async ({page}) => {
    await open(page)
    await caret(page, {after: 'Salva'})
    await page.keyboard.type('abc')
    await page.keyboard.press('ControlOrMeta+z')
    expect(await value(page)).toBe(target)
  })

  test('undoing a tag deletion brings the chip back as a tag', async ({
    page,
  }) => {
    await open(page)
    const chips = await chipTexts(page)
    await caret(page, {before: 'Salva'})
    await page.keyboard.press('Backspace')
    await page.keyboard.press('ControlOrMeta+z')
    expect(await value(page)).toBe(target)
    expect(await chipTexts(page)).toEqual(chips)
    // still one unit: Backspace removes it whole again
    await caret(page, {before: 'Salva'})
    await page.keyboard.press('Backspace')
    expect(await value(page)).toBe(replaceOnce(target, '<g id="3">', ''))
  })
})
