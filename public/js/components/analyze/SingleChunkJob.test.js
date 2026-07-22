import React from 'react'
import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import '@testing-library/jest-dom'
import {fromJS} from 'immutable'
import SingleChunkJob from './SingleChunkJob'
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
    jobs: [{id: 10, password: 'pw1'}],
    analysis: {workflow_type: workflowType},
  })

const buildJob = (overrides = {}) => ({
  id: 10,
  password: 'pw1',
  target_name: 'Italian',
  source_name: 'English',
  outsource_available: true,
  outsource: null,
  chunks: [
    {
      password: 'pw1',
      total_raw: 100,
      total_industry: 80,
      total_equivalent: 60,
      urls: {t: 'http://example.com/translate'},
      outsource_info: {},
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
  getDirectOpenButton: jest.fn((chunk) => (
    <button data-testid="direct-open">Translate {chunk.id}</button>
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

test('renders raw, standard (STANDARD workflow) and equivalent counts', () => {
  const props = buildProps()
  render(<SingleChunkJob {...props} />)

  expect(screen.getByText('100')).toBeInTheDocument() // total_raw
  expect(screen.getByText('80')).toBeInTheDocument() // total_industry
  expect(screen.getByText('60')).toBeInTheDocument() // total_equivalent
})

test('hides the industry column outside of STANDARD workflow', () => {
  const props = buildProps({
    project: buildProject(ANALYSIS_WORKFLOW_TYPES.MTQE),
  })
  render(<SingleChunkJob {...props} />)
  expect(screen.queryByText('80')).not.toBeInTheDocument()
})

test('calls checkPayableChanged with job id and total_equivalent', () => {
  const props = buildProps()
  render(<SingleChunkJob {...props} />)
  expect(props.checkPayableChanged).toHaveBeenCalledWith(10, 60)
})

test('Details button invokes showDetails with the job id', async () => {
  const spy = jest.fn()
  const props = buildProps({
    showDetails: jest.fn((id) => (e) => {
      e.preventDefault()
      spy(id)
    }),
  })
  render(<SingleChunkJob {...props} />)

  await userEvent.click(screen.getByText('Details'))
  expect(spy).toHaveBeenCalledWith(10)
})

test('shows the OutsourceButton when not in jobAnalysis mode and status DONE', () => {
  global.config = {jobAnalysis: false}
  const props = buildProps({status: ANALYSIS_STATUS.DONE})
  render(<SingleChunkJob {...props} />)
  expect(screen.getByText('Buy Translation')).toBeInTheDocument()
})

test('hides the OutsourceButton in jobAnalysis mode', () => {
  global.config = {jobAnalysis: true}
  const props = buildProps({status: ANALYSIS_STATUS.DONE})
  render(<SingleChunkJob {...props} />)
  expect(screen.queryByText('Buy Translation')).not.toBeInTheDocument()
})

test('renders the custom getDirectOpenButton content', () => {
  const props = buildProps()
  render(<SingleChunkJob {...props} />)
  expect(screen.getByTestId('direct-open')).toHaveTextContent('Translate 10')
})

test('passes the resolved chunkJob and open state to OutsourceContainer', () => {
  const props = buildProps({openOutsource: true, outsourceJobId: 10})
  render(<SingleChunkJob {...props} />)

  const call = OutsourceContainer.mock.calls[0][0]
  expect(call.idJobLabel).toBe(10)
  expect(call.standardWC).toBe(60)
  expect(call.showTranslatorBox).toBe(false)
  expect(call.extendedView).toBe(true)
  expect(call.openOutsource).toBe(true)
  expect(call.job).toBeDefined()
})

test('clicking the copy button uses copyJobLinkToClipboard for this job id', async () => {
  const spy = jest.fn()
  const props = buildProps({
    copyJobLinkToClipboard: jest.fn((id) => (e) => {
      spy(id)
    }),
  })
  render(<SingleChunkJob {...props} />)

  const copyButtons = screen.getAllByRole('button')
  // find the copy button by its accessible name (tooltip text) if present, else click first icon-only button
  const copyButton = copyButtons.find((b) => !b.textContent) || copyButtons[0]
  await userEvent.click(copyButton)
  expect(spy).toHaveBeenCalledWith(10)
})
