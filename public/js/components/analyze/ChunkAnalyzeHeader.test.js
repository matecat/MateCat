import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import '@testing-library/jest-dom'
import ChunkAnalyzeHeader from './ChunkAnalyzeHeader'
import {ANALYSIS_WORKFLOW_TYPES} from '../../constants/Constants'

const buildTotal = (overrides = {}) => {
  const base = {
    new: {raw: 10, equivalent: 8},
    repetitions: {raw: 5, equivalent: 3},
    internal: {raw: 4, equivalent: 2},
    tm_50_74: {raw: 6, equivalent: 3},
    tm_75_84: {raw: 7, equivalent: 4},
    tm_85_94: {raw: 8, equivalent: 5},
    tm_95_99: {raw: 9, equivalent: 6},
    tm_100: {raw: 10, equivalent: 1},
    tm_100_public: {raw: 11, equivalent: 1},
    ice: {raw: 12, equivalent: 1},
    MT: {raw: 13, equivalent: 7},
    ice_mt: {raw: 14, equivalent: 9},
    top_quality_mt: {raw: 1, equivalent: 1},
    higher_quality_mt: {raw: 1, equivalent: 1},
    standard_quality_mt: {raw: 1, equivalent: 1},
    ...overrides,
  }
  return Object.entries(base).map(([type, values]) => ({type, ...values}))
}

const jobInfo = {files: [{}, {}], total_raw: 50, total_equivalent: 30}

describe('ChunkAnalyzeHeader - STANDARD workflow', () => {
  test('shows chunk index when chunksSize > 1 and toggles files on click', async () => {
    const showFilesFn = jest.fn()
    render(
      <ChunkAnalyzeHeader
        total={buildTotal()}
        index={2}
        showFilesFn={showFilesFn}
        showFiles={false}
        jobInfo={jobInfo}
        chunksSize={3}
        rates={{}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      />,
    )
    expect(screen.getByText('Chunk 2')).toBeInTheDocument()
    expect(screen.getByText('File (2)')).toBeInTheDocument()

    await userEvent.click(screen.getByText('File (2)'))
    expect(showFilesFn).toHaveBeenCalledTimes(1)
  })

  test('hides chunk index when chunksSize is 1', () => {
    render(
      <ChunkAnalyzeHeader
        total={buildTotal()}
        index={1}
        showFilesFn={jest.fn()}
        showFiles={false}
        jobInfo={jobInfo}
        chunksSize={1}
        rates={{}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      />,
    )
    expect(screen.queryByText(/Chunk/)).not.toBeInTheDocument()
  })

  test('adds the "open" class when showFiles is true', () => {
    const {container} = render(
      <ChunkAnalyzeHeader
        total={buildTotal()}
        index={1}
        showFilesFn={jest.fn()}
        showFiles={true}
        jobInfo={jobInfo}
        chunksSize={1}
        rates={{}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      />,
    )
    expect(
      container.querySelector('.chunk-analyze-info-header.open'),
    ).not.toBeNull()
  })

  test('merges MT and ice_mt raw+equivalent when ICE_MT === MT', () => {
    render(
      <ChunkAnalyzeHeader
        total={buildTotal()}
        index={1}
        showFilesFn={jest.fn()}
        showFiles={false}
        jobInfo={jobInfo}
        chunksSize={1}
        rates={{ICE_MT: 5, MT: 5}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      />,
    )
    // MT raw(13) + ice_mt raw(14) = 27, equivalent(7)+equivalent(9) = 16
    expect(screen.getByText('27')).toBeInTheDocument()
    expect(screen.getByText('16')).toBeInTheDocument()
  })

  test('shows a separate ice_mt column when ICE_MT !== MT and raw > 0', () => {
    const {container} = render(
      <ChunkAnalyzeHeader
        total={buildTotal()}
        index={1}
        showFilesFn={jest.fn()}
        showFiles={false}
        jobInfo={jobInfo}
        chunksSize={1}
        rates={{ICE_MT: 7, MT: 5}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      />,
    )
    expect(container.querySelector('.more-columns')).not.toBeNull()
    expect(screen.getByText('50')).toBeInTheDocument() // jobInfo.total_raw
    expect(screen.getByText('30')).toBeInTheDocument() // jobInfo.total_equivalent
  })

  test('does not show separate ice_mt column when raw is 0', () => {
    const total = buildTotal({ice_mt: {raw: 0, equivalent: 9}})
    const {container} = render(
      <ChunkAnalyzeHeader
        total={total}
        index={1}
        showFilesFn={jest.fn()}
        showFiles={false}
        jobInfo={jobInfo}
        chunksSize={1}
        rates={{ICE_MT: 7, MT: 5}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      />,
    )
    expect(container.querySelector('.more-columns')).toBeNull()
  })
})

describe('ChunkAnalyzeHeader - MTQE workflow', () => {
  test('renders the mtqe column set', () => {
    const {container} = render(
      <ChunkAnalyzeHeader
        total={buildTotal()}
        index={1}
        showFilesFn={jest.fn()}
        showFiles={false}
        jobInfo={jobInfo}
        chunksSize={1}
        rates={{}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.MTQE}
      />,
    )
    expect(container.querySelector('.chunk-analyze-info.mtqe')).not.toBeNull()
    expect(screen.getByText('File (2)')).toBeInTheDocument()
  })
})

describe('ChunkAnalyzeHeader - unknown workflow', () => {
  test('renders nothing', () => {
    const {container} = render(
      <ChunkAnalyzeHeader
        total={buildTotal()}
        index={1}
        showFilesFn={jest.fn()}
        showFiles={false}
        jobInfo={jobInfo}
        chunksSize={1}
        rates={{}}
        workflowType="unknown"
      />,
    )
    expect(container).toBeEmptyDOMElement()
  })
})
