import React from 'react'
import {render, screen, within} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {FileList, splitFileName} from './FileList'
import {
  ANALYSIS_WORKFLOW_TYPES,
  UNIT_COUNT,
} from '../../../../constants/Constants'

const payableRates = {NO_MATCH: 100, '100%': 30, MT: 72, ICE_MT: 72}

const buildFile = (id, name, raw = 100, equivalent = 80) => ({
  id,
  name,
  totals: {raw, equivalent, industry: null},
  matches: [
    {type: 'new', raw: raw - 20, equivalent: equivalent - 6},
    {type: 'tm_100', raw: 20, equivalent: 6},
    {type: 'internal', raw: 0, equivalent: 0},
  ],
})

const renderList = (files) =>
  render(
    <FileList
      files={files}
      payableRates={payableRates}
      workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
    />,
  )

const manyFiles = (count) =>
  Array.from({length: count}, (_, i) =>
    buildFile(
      i + 1,
      i % 2 ? `docs/manual-${i + 1}.docx` : `site/page-${i + 1}.html`,
    ),
  )

test('splits a path into base name and folder', () => {
  expect(splitFileName('a/b/file.docx')).toEqual({
    baseName: 'file.docx',
    folder: 'a/b',
  })
  expect(splitFileName('file.docx')).toEqual({
    baseName: 'file.docx',
    folder: '',
  })
})

test('shows each file with base name, folder and counts', () => {
  renderList([buildFile(1, 'marketing/brochure.docx', 1500, 900)])
  const button = screen.getByRole('button', {name: /brochure\.docx/})
  expect(within(button).getByText('brochure.docx')).toBeInTheDocument()
  expect(within(button).getByText('marketing')).toBeInTheDocument()
  const row = screen.getByRole('row', {name: /brochure\.docx/})
  expect(row).toHaveTextContent('1,500')
  expect(row).toHaveTextContent('900')
  // One file: no search
  expect(screen.queryByRole('searchbox')).not.toBeInTheDocument()
})

test('expands a file to its non-empty buckets', async () => {
  const user = userEvent.setup()
  renderList([buildFile(1, 'a.docx'), buildFile(2, 'b.docx')])

  const button = screen.getByRole('button', {name: 'a.docx'})
  expect(button).toHaveAttribute('aria-expanded', 'false')
  expect(screen.queryByText('TM 100%')).not.toBeInTheDocument()

  await user.click(button)
  expect(button).toHaveAttribute('aria-expanded', 'true')
  const detail = screen.getByRole('table', {name: 'a.docx by analysis bucket'})
  expect(
    within(detail)
      .getAllByRole('columnheader')
      .map((cell) => cell.textContent),
  ).toEqual(['Bucket', 'Raw', 'Weighted'])
  expect(
    within(detail)
      .getAllByRole('rowheader')
      .map((cell) => cell.textContent),
  ).toEqual(['New', 'TM 100%'])
  const cellsOf = (name) =>
    within(within(detail).getByRole('row', {name}))
      .getAllByRole('cell')
      .map((cell) => cell.textContent)
  expect(cellsOf(/^New/)).toEqual(['80', '74'])
  expect(cellsOf(/^TM 100%/)).toEqual(['20', '6'])
  expect(screen.queryByText(/Internal/)).not.toBeInTheDocument()

  await user.click(button)
  expect(screen.queryByText('TM 100%')).not.toBeInTheDocument()
})

test.each([1, 8])('has no search with %p files', (count) => {
  renderList(manyFiles(count))
  expect(screen.queryByRole('searchbox')).not.toBeInTheDocument()
  expect(screen.queryByText(/files match/)).not.toBeInTheDocument()
  expect(screen.getAllByRole('button')).toHaveLength(count)
})

test('searches by name and folder above 8 files', async () => {
  const user = userEvent.setup()
  renderList(manyFiles(9))

  const search = screen.getByRole('searchbox', {name: 'Find a file'})
  expect(screen.getByText('9 of 9 files match')).toBeInTheDocument()

  await user.type(search, 'DOCS/')
  expect(screen.getByText('4 of 9 files match')).toBeInTheDocument()
  expect(screen.getAllByRole('button')).toHaveLength(4)

  await user.clear(search)
  await user.type(search, 'page-3')
  expect(screen.getByText('1 of 9 files match')).toBeInTheDocument()
  expect(screen.getByRole('button', {name: /page-3\.html/})).toBeVisible()
})

test('shows a row when no file matches the search', async () => {
  const user = userEvent.setup()
  renderList(manyFiles(9))
  await user.type(screen.getByRole('searchbox'), 'nothing-like-this')
  expect(screen.getByText('0 of 9 files match')).toBeInTheDocument()
  expect(screen.getByRole('cell', {name: 'No files match'})).toBeVisible()
  expect(screen.queryByRole('button')).not.toBeInTheDocument()
})

test('says which unit is missing for a file with no counts', async () => {
  const user = userEvent.setup()
  render(
    <FileList
      files={[{id: 1, name: 'empty.docx', totals: {raw: 0, equivalent: 0}}]}
      payableRates={payableRates}
      workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      countUnit={UNIT_COUNT.CHARACTERS}
    />,
  )
  await user.click(screen.getByRole('button', {name: 'empty.docx'}))
  expect(
    screen.getByText('No characters to analyze in this file'),
  ).toBeInTheDocument()
})
