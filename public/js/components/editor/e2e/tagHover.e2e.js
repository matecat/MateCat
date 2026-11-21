// Hovering a chip reports whether the chip trims its text, and the full text, for the tag tooltip of the Matecat
// editors (matecat/tagTooltip.js). Trimming is layout, so it is measured here, in a real browser.
import {test} from '@playwright/test'
import {SOURCE, TARGET, events, expect, open} from './helpers'

const LONG =
  '<a·href="https://www.matecat.com/a/very/long/path/that/never/fits/in/a/chip"·class="link">'

for (const [side, pane] of [
  ['source', SOURCE],
  ['target', TARGET],
]) {
  test(`hovering a trimmed ${side} chip reports its full text; a short one is not trimmed`, async ({
    page,
  }) => {
    await open(page, 'longTag')
    const chips = page.locator(`${pane} .tag`)

    const ofSide = async (name) =>
      (await events(page, name)).filter((e) => e.side === side)

    // Each hover is awaited: WebKit can deliver the pointer events after hover() resolves
    await chips.nth(0).hover()
    await expect.poll(async () => (await ofSide('tagHover')).length).toBe(1)
    await chips.nth(1).hover()
    await expect.poll(async () => (await ofSide('tagHover')).length).toBe(2)
    await page.mouse.move(0, 0)
    await expect.poll(async () => (await ofSide('tagLeave')).length).toBe(2)

    const hovers = await ofSide('tagHover')
    expect(hovers.map((e) => e.payload)).toEqual([
      expect.objectContaining({name: 'ph', label: LONG, truncated: true}),
      expect.objectContaining({name: 'ph', label: '<br/>', truncated: false}),
    ])
    // The rect places the tooltip above the chip it explains
    const box = await chips.nth(0).boundingBox()
    const {rect} = hovers[0].payload
    expect(rect.left).toBeCloseTo(box.x, 0)
    expect(rect.top).toBeCloseTo(box.y, 0)
    expect(rect.width).toBeCloseTo(box.width, 0)
  })
}

test('a long chip label ends in an ellipsis, a short one is shown whole', async ({
  page,
}) => {
  await open(page, 'longTag')
  const labels = await page
    .locator(`${TARGET} .tag .tag-text`)
    .evaluateAll((els) =>
      els.map((el) => ({
        textOverflow: getComputedStyle(el).textOverflow,
        trimmed: el.scrollWidth > el.clientWidth,
      })),
    )
  expect(labels).toEqual([
    {textOverflow: 'ellipsis', trimmed: true},
    {textOverflow: 'ellipsis', trimmed: false},
  ])
})

for (const [side, pane] of [
  ['source', SOURCE],
  ['target', TARGET],
]) {
  test(`${side} chips have the size of Draft's open chips, not the closed segment's, and fit in their line`, async ({
    page,
  }) => {
    await open(page)
    const {editor, draft, fits} = await page.evaluate((pane) => {
      // A chip as Draft's TagEntity draws it in an open segment
      const container = document.body.appendChild(
        document.createElement('span'),
      )
      container.className = 'tag-container'
      container.innerHTML =
        '<span class="tag tag-open"><span class="index-counter">1</span><span data-text="true">a</span></span>'
      const look = (chip) => {
        const style = getComputedStyle(chip)
        const arrow = getComputedStyle(chip, '::before')
        const label = getComputedStyle(
          chip.querySelector('.tag-text, [data-text]'),
        )
        return {
          fontSize: style.fontSize,
          lineHeight: style.lineHeight,
          padding: style.padding,
          margin: style.margin,
          arrow: [
            arrow.right,
            arrow.top,
            arrow.borderLeftWidth,
            arrow.borderTopWidth,
            arrow.borderBottomWidth,
          ],
          label: [label.maxWidth, label.textOverflow, label.whiteSpace],
        }
      }
      const chip = document.querySelector(`${pane} .tag.tag-open`)
      const chipBox = chip.getBoundingClientRect()
      const lineBox = chip.closest('.cm-line').getBoundingClientRect()
      return {
        editor: look(chip),
        draft: look(container.firstChild),
        fits: chipBox.top >= lineBox.top && chipBox.bottom <= lineBox.bottom,
      }
    }, pane)
    expect(editor.fontSize).toBe('13px')
    expect(editor).toEqual(draft)
    expect(fits).toBe(true)
  })
}

test('compacted tags hide the label of numbered chips and keep their number', async ({
  page,
}) => {
  await open(page, 'ph')
  const numbered = page.locator(`${TARGET} .tag.tag-numbered`).first()
  await expect(numbered.locator('.tag-text')).toBeVisible()

  await page.evaluate(() => document.body.classList.add('tags-compressed'))
  await expect(numbered.locator('.tag-text')).toBeHidden()
  await expect(numbered.locator('.index-counter')).toBeVisible()
  // A tag that is not numbered keeps its label
  await expect(
    page.locator(`${TARGET} .tag:not(.tag-numbered) .tag-text`).first(),
  ).toBeVisible()
})

test('with compacted tags, a numbered chip holding the current search hit shows its label in the current hit colour', async ({
  page,
}) => {
  await open(page, 'ph')
  await page.evaluate(() => document.body.classList.add('tags-compressed'))
  const numbered = page.locator(`${TARGET} .tag.tag-numbered`)
  // The first numbered chip holds the current hit (search.js gives it both classes), the second none
  await page.evaluate((pane) => {
    const chips = [...document.querySelectorAll(`${pane} .tag`)]
    const first = chips.findIndex((el) => el.matches('.tag-numbered'))
    window.__editor.tagState('target', [
      [first, 'tag-search-match tag-search-current'],
    ])
  }, TARGET)
  await expect(numbered.first()).toHaveClass(/tag-search-current/)
  const hit = numbered.first().locator('.tag-text')
  await expect(hit).toBeVisible()
  expect(await hit.evaluate((el) => getComputedStyle(el).backgroundColor)).toBe(
    'rgb(255, 210, 14)',
  )
  await expect(numbered.nth(1).locator('.tag-text')).toBeHidden()
})
