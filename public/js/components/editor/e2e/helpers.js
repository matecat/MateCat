// Drives the harness page (harness.js) through window.__editor and real keyboard and mouse input.
import {expect} from '@playwright/test'

export const TARGET = '[data-testid=target] .cm-content'
export const SOURCE = '[data-testid=source] .cm-content'

export async function open(page, seg = 'basic', query = '') {
  await page.goto(`/?seg=${seg}${query}`)
  await page.waitForFunction(() => window.__editor?.ready === true)
}

export const value = (page, pane = 'target') =>
  page.evaluate((p) => window.__editor[p].getValue(), pane)

export const call = (page, method, ...args) =>
  page.evaluate(([m, a]) => window.__editor.target[m](...a), [method, args])

export const events = (page, name) =>
  page.evaluate((n) => window.__editor.events.filter((e) => e.name === n), name)

/** Document position of `word` in a pane (its start, or its end with `after`). */
const docPos = (page, pane, word, after) =>
  page.evaluate(
    ([p, w, a]) => {
      const doc = window.__editor.doc(p)
      const i = doc.indexOf(w)
      if (i < 0) throw new Error(`"${w}" is not in "${doc}"`)
      return a ? i + w.length : i
    },
    [pane, word, after],
  )

/** Puts the target caret right before `{before: word}` or right after `{after: word}`. */
export async function caret(page, anchor, pane = 'target') {
  const pos = await docPos(
    page,
    pane,
    anchor.before ?? anchor.after,
    anchor.after !== undefined,
  )
  await page.evaluate(([p, at]) => window.__editor.caret(p, at), [pane, pos])
}

/** Selects from the start of `fromWord` to the end of `toWord`. */
export async function select(page, fromWord, toWord, pane = 'source') {
  const from = await docPos(page, pane, fromWord, false)
  const to = await docPos(page, pane, toWord, true)
  await page.evaluate(
    ([p, f, t]) => window.__editor.select(p, f, t),
    [pane, from, to],
  )
}

/** The chips of a pane as the text they show. */
export const chipTexts = (page, pane = TARGET) =>
  page
    .locator(`${pane} .tag`)
    .evaluateAll((els) => els.map((el) => el.textContent))

/** The chips tokenize() gives for a segment string, as the text the cattool shows for them. */
export const expectedChipTexts = (page, raw) =>
  page.evaluate(
    (r) =>
      window.__editor
        .tokenize(r)
        .filter(
          (t) =>
            t.type === 'tag' &&
            !['lineFeed', 'nbsp', 'tab', 'wordJoiner', 'space'].includes(
              t.name,
            ),
        )
        .map((t) =>
          t.index !== undefined && t.index !== null
            ? String(t.index + 1) + (t.role === 'close' ? '' : t.label)
            : t.label,
        ),
    raw,
  )

/** Rect of the first rendered occurrence of `word` in a pane. */
export const textRect = (page, word, pane = TARGET) =>
  page.locator(pane).evaluate((root, w) => {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
    while (walker.nextNode()) {
      const node = walker.currentNode
      const i = node.data.indexOf(w)
      if (i >= 0 && !node.parentElement.closest('.tag')) {
        const range = document.createRange()
        range.setStart(node, i)
        range.setEnd(node, i + w.length)
        const {top, bottom, left, right} = range.getBoundingClientRect()
        return {top, bottom, left, right}
      }
    }
    throw new Error(`"${w}" is not rendered`)
  }, word)

/** Replaces the first occurrence of `what`, failing loudly when the fixture changed. */
export function replaceOnce(raw, what, by) {
  const i = raw.indexOf(what)
  if (i < 0) throw new Error(`fixture does not contain ${what}`)
  return raw.slice(0, i) + by + raw.slice(i + what.length)
}

/** Dispatches a synthetic clipboard event carrying `data` ({type: value}) on a pane; returns what the editor wrote. */
export const clipboardEvent = (page, type, data = {}, pane = TARGET) =>
  page.locator(pane).evaluate(
    (el, [t, d]) => {
      const dt = new DataTransfer()
      for (const [k, v] of Object.entries(d)) dt.setData(k, v)
      const event = new ClipboardEvent(t, {
        clipboardData: dt,
        bubbles: true,
        cancelable: true,
      })
      // Firefox and WebKit drop clipboardData from a constructed event
      if (event.clipboardData !== dt)
        Object.defineProperty(event, 'clipboardData', {value: dt})
      el.dispatchEvent(event)
      return {plain: dt.getData('text/plain'), html: dt.getData('text/html')}
    },
    [type, data],
  )

export {expect}
