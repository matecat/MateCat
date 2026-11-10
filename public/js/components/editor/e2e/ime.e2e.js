// Contract item 9 of the editor spike: IME composition next to a tag (Chromium, through CDP).
import {test} from '@playwright/test'
import {SEGMENTS} from './fixtures'
import {
  TARGET,
  call,
  caret,
  chipTexts,
  events,
  expect,
  open,
  replaceOnce,
  value,
} from './helpers'

test.skip(
  ({browserName}) => browserName !== 'chromium',
  'IME is driven through the Chromium DevTools protocol',
)

const {target} = SEGMENTS.basic

/** Composes 한 (ㅎ → 하 → 한) and commits it, as a Korean IME does. */
async function composeKorean(page) {
  const cdp = await page.context().newCDPSession(page)
  for (const text of ['ㅎ', '하', '한']) {
    await cdp.send('Input.imeSetComposition', {
      text,
      selectionStart: text.length,
      selectionEnd: text.length,
    })
  }
  await cdp.send('Input.insertText', {text: '한'})
}

test('composition right after a tag leaves the tag intact', async ({page}) => {
  await open(page)
  const chips = await chipTexts(page)
  await caret(page, {before: 'Salva'})
  await composeKorean(page)
  expect(await value(page)).toBe(replaceOnce(target, 'Salva', '한Salva'))
  expect(await chipTexts(page)).toEqual(chips)
})

test('composition right before a tag leaves the tag intact', async ({page}) => {
  await open(page)
  await caret(page, {after: 'Salva'})
  await composeKorean(page)
  expect(await value(page)).toBe(replaceOnce(target, 'Salva', 'Salva한'))
})

test('composition in an empty target, then a tag inserted after it', async ({
  page,
}) => {
  await open(page)
  await page.evaluate(() =>
    window.__editor.target.setValue('', {undoable: false}),
  )
  await page.locator(TARGET).focus()
  await composeKorean(page)
  await page.evaluate(() => window.__editor.target.insertRaw('<x id="2"/>'))
  expect(await value(page)).toBe('한<x id="2"/>')
})

test('a composed word, then a tag from the tag menu right after it', async ({
  page,
}) => {
  await open(page)
  await caret(page, {before: 'Seconda'})
  await composeKorean(page)
  await page.keyboard.type('<')
  await expect
    .poll(async () => (await events(page, 'tagMenu')).at(-1)?.payload)
    .not.toBeNull()
  await call(page, 'insertMenuTag', '<x id="9"/>')
  expect(await value(page)).toBe(
    replaceOnce(target, 'Seconda', '\ud55c<x id="9"/>Seconda'),
  )
})

test('a layer update during a composition waits for it to end, and then marks the right text', async ({
  page,
}) => {
  await open(page)
  await caret(page, {before: 'Salva'})
  const cdp = await page.context().newCDPSession(page)
  for (const text of ['ㅎ', '하']) {
    await cdp.send('Input.imeSetComposition', {
      text,
      selectionStart: text.length,
      selectionEnd: text.length,
    })
  }
  const now = await page.evaluate(() =>
    window.__editor.layerOnWord('lexiqa', 'Salva', 'lexiqahighlight'),
  )
  expect(now).toBe(false)
  await expect(page.locator(`${TARGET} [data-layer=lexiqa]`)).toHaveCount(0)

  await cdp.send('Input.insertText', {text: '한'})
  await expect(page.locator(`${TARGET} [data-layer=lexiqa]`)).toHaveText(
    'Salva',
  )
  expect(await value(page)).toBe(replaceOnce(target, 'Salva', '한Salva'))
})
