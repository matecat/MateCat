import React from 'react'
import {render, screen} from '@testing-library/react'
import '@testing-library/jest-dom'
import ChunkAnalyzeFile from './ChunkAnalyzeFile'
import {ANALYSIS_WORKFLOW_TYPES} from '../../constants/Constants'

const buildMatches = (overrides = {}) => {
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

const buildFile = (matchesOverrides = {}) => ({
  name: 'document.docx',
  matches: buildMatches(matchesOverrides),
  total_equivalent: 42,
})

describe('ChunkAnalyzeFile - STANDARD workflow', () => {
  test('renders basic columns and single MT column when ICE_MT is not set', () => {
    const file = buildFile()
    render(
      <ChunkAnalyzeFile
        file={file}
        index={1}
        size={1}
        rates={{}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      />,
    )
    expect(screen.getByText('document.docx')).toBeInTheDocument()
    expect(screen.getByText('42')).toBeInTheDocument()
    // MT column shows plain MT equivalent (7), not merged
    expect(screen.getByText('7')).toBeInTheDocument()
  })

  test('merges MT and ice_mt into one cell when ICE_MT === MT', () => {
    const file = buildFile()
    const {container} = render(
      <ChunkAnalyzeFile
        file={file}
        index={1}
        size={1}
        rates={{ICE_MT: 5, MT: 5}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      />,
    )
    // MT (7) + ice_mt (9) === 16 merged into a single cell
    expect(screen.getByText('16')).toBeInTheDocument()
    expect(container.querySelector('.more-columns')).toBeNull()
  })

  test('shows an extra ice_mt column when ICE_MT differs from MT and raw > 0', () => {
    const file = buildFile()
    const {container} = render(
      <ChunkAnalyzeFile
        file={file}
        index={1}
        size={1}
        rates={{ICE_MT: 7, MT: 5}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      />,
    )
    expect(container.querySelector('.more-columns')).not.toBeNull()
    // both plain MT (7) and separate ice_mt (9) equivalent values are shown
    expect(screen.getByText('7')).toBeInTheDocument()
    expect(screen.getByText('9')).toBeInTheDocument()
  })

  test('does not show extra column when ice_mt raw is 0', () => {
    const file = buildFile({ice_mt: {raw: 0, equivalent: 9}})
    const {container} = render(
      <ChunkAnalyzeFile
        file={file}
        index={1}
        size={2}
        rates={{ICE_MT: 7, MT: 5}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      />,
    )
    expect(container.querySelector('.more-columns')).toBeNull()
  })

  test('applies the "last" background class when index equals size', () => {
    const file = buildFile()
    const {container} = render(
      <ChunkAnalyzeFile
        file={file}
        index={2}
        size={2}
        rates={{}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.STANDARD}
      />,
    )
    expect(
      container.querySelector('.chunk-file-detail-background.last'),
    ).not.toBeNull()
  })
})

describe('ChunkAnalyzeFile - MTQE workflow', () => {
  test('renders the mtqe column set', () => {
    const file = buildFile()
    const {container} = render(
      <ChunkAnalyzeFile
        file={file}
        index={1}
        size={1}
        rates={{}}
        workflowType={ANALYSIS_WORKFLOW_TYPES.MTQE}
      />,
    )
    expect(container.querySelector('.chunk-file-detail.mtqe')).not.toBeNull()
    expect(screen.getByText('document.docx')).toBeInTheDocument()
    expect(screen.getByText('42')).toBeInTheDocument()
  })
})

describe('ChunkAnalyzeFile - unknown workflow', () => {
  test('renders nothing', () => {
    const file = buildFile()
    const {container} = render(
      <ChunkAnalyzeFile
        file={file}
        index={1}
        size={1}
        rates={{}}
        workflowType="unknown"
      />,
    )
    expect(container).toBeEmptyDOMElement()
  })
})
