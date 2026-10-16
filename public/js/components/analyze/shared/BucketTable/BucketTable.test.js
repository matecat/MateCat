import React from 'react'
import {render, screen, within} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {BucketTable} from './BucketTable'
import {
  ANALYSIS_WORKFLOW_TYPES,
  UNIT_COUNT,
} from '../../../../constants/Constants'

const payableRates = {
  NO_MATCH: 100,
  REPETITIONS: 30,
  INTERNAL: 60,
  '50%-74%': 100,
  '75%-84%': 60,
  '85%-94%': 60,
  '95%-99%': 60,
  '100%': 30,
  '100%_PUBLIC': 30,
  ICE: 0,
  MT: 72,
  ICE_MT: 72,
}

const matches = [
  {type: 'new', raw: 600, equivalent: 600},
  {type: 'tm_100', raw: 300, equivalent: 90},
  {type: 'MT', raw: 80, equivalent: 57.6},
  {type: 'ice_mt', raw: 20, equivalent: 14.4},
]

const renderTable = (props = {}) =>
  render(
    <BucketTable
      matches={matches}
      payableRates={payableRates}
      workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      {...props}
    />,
  )

const bodyRows = () =>
  within(screen.getAllByRole('rowgroup')[1]).getAllByRole('row')

const cellsOf = (row) =>
  [within(row).getByRole('rowheader'), ...within(row).getAllByRole('cell')].map(
    (cell) => cell.textContent,
  )

test('lists only the non-empty buckets, with rate, raw and weighted', () => {
  renderTable()
  const rows = bodyRows()
  expect(rows).toHaveLength(3)
  expect(cellsOf(rows[0])).toEqual(['New', '100%', '600', '600'])
  expect(cellsOf(rows[1])).toEqual(['TM 100%', '30%', '300', '90'])
  // ice_mt has the MT rate: its counts are added to MT
  expect(cellsOf(rows[2])).toEqual(['MT', '72%', '100', '72'])
  expect(screen.getByRole('columnheader', {name: 'Weighted'})).toBeVisible()
})

test('falls back to the sum of the buckets without totals', () => {
  renderTable()
  const total = screen.getByRole('row', {name: /Total/})
  expect(cellsOf(total)).toEqual(['Total', '', '1,000', '762'])
})

test('shows the server totals over the sum of the buckets', () => {
  renderTable({
    matches: [
      {type: 'new', raw: 12, equivalent: 12},
      {type: 'tm_100', raw: 10, equivalent: 8},
    ],
    totals: {raw: 22, equivalent: 21},
  })
  const total = screen.getByRole('row', {name: /Total/})
  expect(cellsOf(total)).toEqual(['Total', '', '22', '21'])
})

test('toggles the empty buckets', async () => {
  const user = userEvent.setup()
  renderTable()

  // 11 listed buckets once ice_mt is merged into MT, 8 of them empty
  const toggle = screen.getByRole('button', {
    name: 'Show all 11 buckets (8 empty)',
  })
  expect(toggle).toHaveAttribute('aria-expanded', 'false')
  expect(toggle).toHaveAttribute(
    'aria-controls',
    screen.getAllByRole('rowgroup')[1].id,
  )

  await user.click(toggle)
  expect(bodyRows()).toHaveLength(11)
  expect(screen.getByRole('rowheader', {name: 'Internal 75-99%'})).toBeVisible()
  expect(toggle).toHaveTextContent('Hide empty buckets')
  expect(toggle).toHaveAttribute('aria-expanded', 'true')

  await user.click(toggle)
  expect(bodyRows()).toHaveLength(3)
})

test('counts the MTQE buckets in the toggle', () => {
  renderTable({
    matches: [{type: 'ice', raw: 10, equivalent: 0}],
    workflowType: ANALYSIS_WORKFLOW_TYPES.MTQE,
    payableRates: {ice: 0},
  })
  expect(
    screen.getByRole('button', {name: 'Show all 8 buckets (7 empty)'}),
  ).toBeInTheDocument()
})

test('labels the bar with where the words come from', () => {
  renderTable()
  expect(
    screen.getByRole('img', {
      name: 'Where the 1,000 words come from: New 600 (60%), TM 100% 300 (30%), MT 100 (10%)',
    }),
  ).toBeInTheDocument()
})

test('uses characters in the labels for a character count', () => {
  renderTable({countUnit: UNIT_COUNT.CHARACTERS})
  expect(screen.getByRole('img')).toHaveAccessibleName(
    /^Where the 1,000 characters come from/,
  )
  expect(
    screen.getByRole('table', {name: 'Analysis by bucket, in characters'}),
  ).toBeInTheDocument()
})

test('has no bar when there are no words', () => {
  renderTable({matches: []})
  expect(screen.queryByRole('img')).not.toBeInTheDocument()
  expect(cellsOf(screen.getByRole('row', {name: /Total/}))).toEqual([
    'Total',
    '',
    '0',
    '0',
  ])
})

test('renders nothing for an unknown workflow', () => {
  // The workflow propType warns about the invalid value on purpose
  const consoleError = jest.spyOn(console, 'error').mockImplementation(() => {})
  const {container} = renderTable({workflowType: 'other'})
  expect(container).toBeEmptyDOMElement()
  consoleError.mockRestore()
})
