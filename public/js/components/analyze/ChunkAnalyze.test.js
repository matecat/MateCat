import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import '@testing-library/jest-dom'
import ChunkAnalyze from './ChunkAnalyze'
import {ANALYSIS_WORKFLOW_TYPES} from '../../constants/Constants'

const matchTypes = [
  'new',
  'repetitions',
  'internal',
  'tm_50_74',
  'tm_75_84',
  'tm_85_94',
  'tm_95_99',
  'tm_100',
  'tm_100_public',
  'ice',
  'MT',
  'ice_mt',
]

const buildMatches = () =>
  matchTypes.map((type) => ({type, raw: 1, equivalent: 1}))

const files = [
  {name: 'first.docx', matches: buildMatches(), total_equivalent: 10},
  {name: 'second.docx', matches: buildMatches(), total_equivalent: 20},
]

const total = buildMatches()
const chunkInfo = {files, total_raw: 30, total_equivalent: 30}

test('files are hidden by default and toggle open/closed on click', async () => {
  render(
    <ChunkAnalyze
      files={files}
      chunkInfo={chunkInfo}
      index={1}
      total={total}
      chunksSize={1}
      rates={{}}
      workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
    />,
  )

  expect(screen.queryByText('first.docx')).not.toBeInTheDocument()

  await userEvent.click(screen.getByText('File (2)'))
  expect(screen.getByText('first.docx')).toBeInTheDocument()
  expect(screen.getByText('second.docx')).toBeInTheDocument()

  await userEvent.click(screen.getByText('File (2)'))
  expect(screen.queryByText('first.docx')).not.toBeInTheDocument()
})
