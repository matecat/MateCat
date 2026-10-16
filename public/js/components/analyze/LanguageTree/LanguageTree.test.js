import fs from 'fs'
import path from 'path'
import React from 'react'
import {act, fireEvent, render, screen, within} from '@testing-library/react'
import {buildVolumeAnalysis} from '../../../../mocks/volumeAnalysisMock'
import {deriveAnalysisView} from '../utils/deriveAnalysisView'
import CommonUtils from '../../../utils/commonUtils'
import {
  ACTIONS_DISABLED_TITLE,
  CUSTOM_BILLING_MESSAGE,
  LanguageTree,
} from './LanguageTree'

// The real tooltip renders into a portal root the test page lacks
jest.mock('../../common/Tooltip', () => {
  const MockTooltip = ({content, children}) => (
    <span title={content}>{children}</span>
  )
  return {__esModule: true, default: MockTooltip, TOOLTIP_POSITION: {}}
})

jest.mock('./LanguageTree.module.scss', () => ({
  __esModule: true,
  default: {
    stickyHead: 'stickyHead',
    stickyTotal: 'stickyTotal',
    selected: 'selected',
    copied: 'copied',
    copyFallback: 'copyFallback',
    table: 'table',
    countColumn: 'countColumn',
    actionsColumn: 'actionsColumn',
  },
}))

const getView = (summary) =>
  deriveAnalysisView({analysis: buildVolumeAnalysis({summary})})

const treeElement = (view, props = {}) => (
  <LanguageTree
    jobs={view.jobs}
    workflowType="standard"
    known={view.known}
    actionsEnabled={view.actionsEnabled}
    totals={view.totals}
    selection={{jobId: 101, chunkIndex: null}}
    onSelect={jest.fn()}
    onBuy={jest.fn()}
    {...props}
  />
)

const renderTree = ({summary, view = getView(summary), ...props} = {}) => {
  const onSelect = jest.fn()
  const onBuy = jest.fn()
  const utils = render(treeElement(view, {onSelect, onBuy, ...props}))
  return {...utils, view, onSelect, onBuy}
}

// A hidden row has no accessible name: find it by its text instead
const chunkRow = (name) =>
  screen
    .getAllByRole('row', {hidden: true})
    .find((row) => within(row).queryByText(name))
const jobRow = (pair) =>
  screen.getByRole('row', {
    name: new RegExp(`^(Chunks of ${pair} )?${pair} ID`),
  })
const frenchRow = () => jobRow('en-US › fr-FR')
const flush = () => act(() => Promise.resolve())

// The compiled rules of a class in the component's own stylesheet
const SCSS = fs.readFileSync(
  path.join(__dirname, 'LanguageTree.module.scss'),
  'utf8',
)
const rulesOf = (className) =>
  SCSS.match(new RegExp(`\\n\\.${className} \\{\\n([^]*?)\\n\\}\\n`))?.[1] ?? ''

describe('LanguageTree', () => {
  beforeEach(() => {
    global.config = {...global.config, enable_outsource: true, id_project: 20}
    jest.spyOn(window, 'open').mockImplementation(() => null)
    jest.spyOn(CommonUtils, 'dispatchAnalyticsEvents').mockImplementation()
    Object.defineProperty(navigator, 'clipboard', {
      configurable: true,
      value: {writeText: jest.fn(() => Promise.resolve())},
    })
  })

  afterEach(() => {
    jest.restoreAllMocks()
    jest.useRealTimers()
  })

  test('shows one row per job with its pair, ID and chunk count', () => {
    renderTree()

    const italian = screen.getByRole('button', {name: /^en-US › it-IT ID/})
    expect(italian).toHaveTextContent('ID 101 · split into 2 chunks')
    const french = screen.getByRole('button', {name: /^en-US › fr-FR ID/})
    expect(french).toHaveTextContent('ID 102')
    expect(french).not.toHaveTextContent('split')
    expect(
      screen.getByRole('columnheader', {name: 'Industry weighted'}),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('rowheader', {name: 'Project total'}),
    ).toBeInTheDocument()
  })

  test('hides the industry column outside the standard workflow', () => {
    renderTree({workflowType: 'mtqe'})
    expect(
      screen.queryByRole('columnheader', {name: 'Industry weighted'}),
    ).not.toBeInTheDocument()
  })

  test('chunks are collapsed until the chevron expands them', () => {
    renderTree()

    const chevron = screen.getByRole('button', {
      name: 'Chunks of en-US › it-IT',
    })
    expect(chevron).toHaveAttribute('aria-expanded', 'false')
    expect(chunkRow('Chunk 1')).not.toBeVisible()

    fireEvent.click(chevron)
    expect(chevron).toHaveAttribute('aria-expanded', 'true')
    expect(chunkRow('Chunk 1')).toBeVisible()
    expect(chunkRow('Chunk 2')).toBeVisible()
    // The chevron controls exactly the chunk rows
    expect(chevron.getAttribute('aria-controls').split(' ')).toEqual([
      chunkRow('Chunk 1').id,
      chunkRow('Chunk 2').id,
    ])

    fireEvent.click(chevron)
    expect(chunkRow('Chunk 1')).not.toBeVisible()
  })

  test('selecting a job or a chunk reports it and marks the selected row', () => {
    const {onSelect, rerender, view} = renderTree()

    const italian = screen.getByRole('button', {name: /^en-US › it-IT ID/})
    expect(italian).toHaveAttribute('aria-current', 'true')
    expect(jobRow('en-US › it-IT')).toHaveClass('selected')
    expect(
      screen.getByRole('button', {name: /^en-US › fr-FR ID/}),
    ).not.toHaveAttribute('aria-current')

    fireEvent.click(screen.getByRole('button', {name: /^en-US › fr-FR ID/}))
    expect(onSelect).toHaveBeenLastCalledWith({jobId: 102, chunkIndex: null})

    fireEvent.click(
      screen.getByRole('button', {name: 'Chunks of en-US › it-IT'}),
    )
    fireEvent.click(
      screen.getByRole('button', {name: 'en-US › it-IT, chunk 2'}),
    )
    expect(onSelect).toHaveBeenLastCalledWith({jobId: 101, chunkIndex: 2})

    rerender(
      treeElement(view, {selection: {jobId: 101, chunkIndex: 2}, onSelect}),
    )
    expect(
      screen.getByRole('button', {name: 'en-US › it-IT, chunk 2'}),
    ).toHaveAttribute('aria-current', 'true')
    expect(chunkRow('Chunk 2')).toHaveClass('selected')
    expect(
      screen.getByRole('button', {name: /^en-US › it-IT ID/}),
    ).not.toHaveAttribute('aria-current')
  })

  test('clicking anywhere on a row selects it, except on its own controls', () => {
    const {onSelect} = renderTree()

    fireEvent.click(within(jobRow('en-US › fr-FR')).getAllByRole('cell')[0])
    expect(onSelect).toHaveBeenLastCalledWith({jobId: 102, chunkIndex: null})

    fireEvent.click(
      screen.getByRole('button', {name: 'Chunks of en-US › it-IT'}),
    )
    fireEvent.click(within(chunkRow('Chunk 2')).getAllByRole('cell')[0])
    expect(onSelect).toHaveBeenLastCalledWith({jobId: 101, chunkIndex: 2})

    onSelect.mockClear()
    fireEvent.click(screen.getByRole('button', {name: 'Copy the fr-FR link'}))
    expect(onSelect).not.toHaveBeenCalled()
  })

  test('only unsplit jobs and chunks have row actions', () => {
    renderTree()
    expect(
      within(jobRow('en-US › it-IT')).queryByRole('button', {
        name: 'Translate',
      }),
    ).not.toBeInTheDocument()

    expect(
      screen.getByRole('button', {name: 'Copy the fr-FR link'}),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', {name: 'Copy the chunk 1 link', hidden: true}),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', {name: 'Copy the chunk 2 link', hidden: true}),
    ).toBeInTheDocument()
  })

  test('actions are disabled and explained until the analysis is complete', () => {
    renderTree({summary: {status: 'FAST_OK', segments_analyzed: 10}})

    // On wrappers: Firefox shows no title on a disabled button
    const hints = within(frenchRow()).getAllByTitle(ACTIONS_DISABLED_TITLE)
    expect(hints).toHaveLength(3)
    hints.forEach((hint) =>
      expect(within(hint).getByRole('button')).toBeDisabled(),
    )
    expect(
      within(frenchRow()).getByRole('button', {name: 'Copy the fr-FR link'}),
    ).toBeDisabled()
  })

  test('no hint wrapper once the analysis is complete', () => {
    renderTree()
    expect(
      within(frenchRow()).getByRole('button', {name: 'Translate'}),
    ).toBeEnabled()
    expect(
      within(frenchRow()).queryByTitle(ACTIONS_DISABLED_TITLE),
    ).not.toBeInTheDocument()
  })

  test('weighted values are pending in queue and shown live while analyzing', () => {
    const {unmount} = renderTree({
      summary: {status: 'NEW', in_queue_before: 3},
    })
    // Raw known, industry and weighted pending
    expect(within(frenchRow()).getAllByTestId('pending')).toHaveLength(2)
    expect(frenchRow()).toHaveTextContent('120')
    unmount()

    renderTree({summary: {status: 'FAST_OK', segments_analyzed: 10}})
    expect(within(frenchRow()).queryAllByTestId('pending')).toHaveLength(0)
  })

  test('copy writes the encoded chunk link and confirms it', async () => {
    jest.useFakeTimers()
    const {view} = renderTree()

    fireEvent.click(
      screen.getByRole('button', {name: 'Copy the chunk 2 link', hidden: true}),
    )
    await flush()
    expect(navigator.clipboard.writeText).toHaveBeenCalledWith(
      encodeURI(view.jobs[0].chunks[1].urls.t),
    )
    expect(screen.getByText('Link copied')).toBeInTheDocument()

    act(() => jest.advanceTimersByTime(2000))
    expect(screen.queryByText('Link copied')).not.toBeInTheDocument()
  })

  test.each([
    [
      'rejects',
      () => ({writeText: jest.fn(() => Promise.reject(new Error('denied')))}),
    ],
    ['is missing', () => undefined],
  ])(
    'when the clipboard %s the link is shown to copy by hand',
    async (_, clipboard) => {
      Object.defineProperty(navigator, 'clipboard', {
        configurable: true,
        value: clipboard(),
      })
      const {view} = renderTree()

      fireEvent.click(
        within(frenchRow()).getByRole('button', {name: 'Copy the fr-FR link'}),
      )
      await flush()

      expect(screen.queryByText('Link copied')).not.toBeInTheDocument()
      const input = screen.getByRole('textbox', {name: 'Copy this link'})
      expect(input).toHaveValue(encodeURI(view.jobs[1].chunks[0].urls.t))
      expect(input).toHaveAttribute('readonly')
      expect(input).toHaveFocus()

      fireEvent.keyDown(input, {key: 'Escape'})
      expect(
        screen.queryByRole('textbox', {name: 'Copy this link'}),
      ).not.toBeInTheDocument()
    },
  )

  test('the copy confirmation overlays the row instead of joining its flow', async () => {
    renderTree()
    fireEvent.click(
      within(frenchRow()).getByRole('button', {name: 'Copy the fr-FR link'}),
    )
    await flush()

    const confirmation = within(frenchRow()).getByRole('status')
    expect(confirmation).toHaveTextContent('Link copied')
    expect(confirmation).toHaveClass('copied')
    expect(rulesOf('copied')).toMatch(/^\s*position: absolute;/m)
    // The clipboard fallback is an overlay too
    expect(rulesOf('copyFallback')).toMatch(/^\s*position: absolute;/m)
  })

  test('fixed column widths keep the actions inside the card', () => {
    renderTree()
    expect(screen.getByRole('table')).toHaveClass('table')
    expect(rulesOf('table')).toMatch(/^\s*table-layout: fixed;/m)
    expect(rulesOf('actionsColumn')).toMatch(/^\s*width: \d+px;/m)
    expect(rulesOf('countColumn')).toMatch(/^\s*width: \d+px;/m)
  })

  test('translate opens the chunk in the CAT tool', () => {
    const {view} = renderTree()
    fireEvent.click(
      within(frenchRow()).getByRole('button', {name: 'Translate'}),
    )
    expect(window.open).toHaveBeenCalledWith(
      view.jobs[1].chunks[0].urls.t,
      '_blank',
    )
  })

  test('buy reports the chunk; the event is the page’s to fire', () => {
    const {onBuy, view} = renderTree()
    fireEvent.click(
      within(frenchRow()).getByRole('button', {name: 'Buy translation'}),
    )
    expect(onBuy).toHaveBeenCalledWith(view.jobs[1], view.jobs[1].chunks[0])
    expect(CommonUtils.dispatchAnalyticsEvents).not.toHaveBeenCalled()
  })

  test('a custom billing job cannot be bought and says why', () => {
    const view = getView()
    view.jobs[1] = {
      ...view.jobs[1],
      outsourceAvailable: false,
      outsourceInfo: {custom_payable_rate: true},
    }
    const {onBuy} = renderTree({view})

    const buy = within(frenchRow()).getByRole('button', {
      name: 'Buy translation',
    })
    expect(buy).toBeDisabled()
    expect(
      within(within(frenchRow()).getByTitle(CUSTOM_BILLING_MESSAGE)).getByRole(
        'button',
      ),
    ).toBe(buy)
    // Not an analysis-pending hint: the job can never be bought
    expect(
      within(frenchRow()).queryByTitle(ACTIONS_DISABLED_TITLE),
    ).not.toBeInTheDocument()

    fireEvent.click(buy)
    expect(onBuy).not.toHaveBeenCalled()
    expect(CommonUtils.dispatchAnalyticsEvents).not.toHaveBeenCalled()
  })

  test('an ordered chunk reads View order', () => {
    renderTree({outsourcedChunks: new Set(['102-frpass'])})
    expect(
      within(frenchRow()).getByRole('button', {name: 'View order'}),
    ).toBeInTheDocument()
  })

  test('no buy button when outsourcing is off', () => {
    global.config.enable_outsource = false
    renderTree()
    expect(
      screen.queryByRole('button', {name: 'Buy translation'}),
    ).not.toBeInTheDocument()
  })

  test('the header row and the project total stick', () => {
    renderTree()
    const [head, , total] = screen.getAllByRole('rowgroup')
    expect(head).toHaveClass('stickyHead')
    expect(total).toHaveClass('stickyTotal')
  })
})
