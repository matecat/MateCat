import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import '@testing-library/jest-dom'
import {fromJS} from 'immutable'
import SplitChunkJob from './SplitChunkJob'
import {
  ANALYSIS_STATUS,
  ANALYSIS_WORKFLOW_TYPES,
} from '../../constants/Constants'

jest.mock('../outsource/OutsourceContainer', () => ({
  __esModule: true,
  default: jest.fn(() => <div data-testid="outsource-container-mock" />),
}))

import OutsourceContainer from '../outsource/OutsourceContainer'

const buildProject = (workflowType = ANALYSIS_WORKFLOW_TYPES.STANDARD) =>
  fromJS({
    jobs: [{id: 20, password: 'pw1'}],
    analysis: {workflow_type: workflowType},
  })

const buildJob = (overrides = {}) => ({
  id: 20,
  password: 'pw1',
  target_name: 'Italian',
  source_name: 'English',
  outsource_available: true,
  chunks: [
    {
      password: 'pw1',
      total_raw: 11,
      total_industry: 6,
      total_equivalent: 3,
      urls: {t: 'http://example.com/1'},
    },
    {
      password: 'pw1',
      total_raw: 21,
      total_industry: 9,
      total_equivalent: 4,
      urls: {t: 'http://example.com/2'},
    },
  ],
  ...overrides,
})

const buildProps = (overrides = {}) => ({
  job: buildJob(),
  project: buildProject(),
  status: ANALYSIS_STATUS.DONE,
  openOutsource: false,
  outsourceJobId: null,
  showDetails: jest.fn((id) => (e) => e && e.preventDefault),
  copyJobLinkToClipboard: jest.fn((id) => (e) => e && e.stopPropagation),
  checkPayableChanged: jest.fn(),
  getDirectOpenButton: jest.fn((chunk, index) => (
    <button data-testid={`direct-open-${index}`}>Translate</button>
  )),
  closeOutsourceModal: jest.fn(),
  handleOpenOutsourceModal: jest.fn(
    (id, chunk) => (e) => e && e.stopPropagation,
  ),
  jobLinkRef: {current: {}},
  containers: {current: {}},
  ...overrides,
})

beforeEach(() => {
  global.config = {jobAnalysis: false}
  jest.clearAllMocks()
})

test('renders one card per chunk with its own chunk label and totals', () => {
  const props = buildProps()
  render(<SplitChunkJob {...props} />)

  expect(screen.getByText('Chunk 1')).toBeInTheDocument()
  expect(screen.getByText('Chunk 2')).toBeInTheDocument()
  expect(screen.getByText('11')).toBeInTheDocument()
  expect(screen.getByText('6')).toBeInTheDocument()
  expect(screen.getByText('21')).toBeInTheDocument()
})

test('hides the industry column outside of STANDARD workflow', () => {
  const props = buildProps({
    project: buildProject(ANALYSIS_WORKFLOW_TYPES.MTQE),
  })
  render(<SplitChunkJob {...props} />)
  expect(screen.queryByText('6')).not.toBeInTheDocument()
})

test('calls checkPayableChanged per-chunk with job.id + index', () => {
  const props = buildProps()
  render(<SplitChunkJob {...props} />)
  expect(props.checkPayableChanged).toHaveBeenCalledWith(21, 3)
  expect(props.checkPayableChanged).toHaveBeenCalledWith(22, 4)
})

test('Details buttons invoke showDetails with the job id for each chunk', async () => {
  const spy = jest.fn()
  const props = buildProps({
    showDetails: jest.fn((id) => (e) => {
      e.preventDefault()
      spy(id)
    }),
  })
  render(<SplitChunkJob {...props} />)

  const detailsButtons = screen.getAllByText('Details')
  expect(detailsButtons).toHaveLength(2)
  await userEvent.click(detailsButtons[0])
  expect(spy).toHaveBeenCalledWith(20)
})

test('renders the custom getDirectOpenButton content for each chunk', () => {
  const props = buildProps()
  render(<SplitChunkJob {...props} />)
  expect(screen.getByTestId('direct-open-20-1')).toBeInTheDocument()
  expect(screen.getByTestId('direct-open-20-2')).toBeInTheDocument()
})

test('renders OutsourceContainer per chunk with the composite idJobLabel', () => {
  const props = buildProps({openOutsource: true, outsourceJobId: '20-2'})
  render(<SplitChunkJob {...props} />)

  expect(OutsourceContainer).toHaveBeenCalledTimes(2)
  const calls = OutsourceContainer.mock.calls.map((c) => c[0])
  expect(calls[0].idJobLabel).toBe('20-1')
  expect(calls[1].idJobLabel).toBe('20-2')
  expect(calls[0].openOutsource).toBe(false)
  expect(calls[1].openOutsource).toBe(true)
})

test('shows an OutsourceButton per chunk when not in jobAnalysis mode and status DONE', () => {
  const props = buildProps()
  render(<SplitChunkJob {...props} />)
  expect(screen.getAllByText('Buy Translation')).toHaveLength(2)
})

test('hides OutsourceButtons in jobAnalysis mode', () => {
  global.config = {jobAnalysis: true}
  const props = buildProps()
  render(<SplitChunkJob {...props} />)
  expect(screen.queryByText('Buy Translation')).not.toBeInTheDocument()
})
