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
