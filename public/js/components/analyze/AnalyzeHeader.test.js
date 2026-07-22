import React from 'react'
import {render, screen, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import '@testing-library/jest-dom'
import {fromJS} from 'immutable'
import AnalyzeHeader from './AnalyzeHeader'
import {ANALYSIS_STATUS} from '../../constants/Constants'

jest.mock('../../api/downloadAnalysisReport')
import {downloadAnalysisReport} from '../../api/downloadAnalysisReport'

const buildData = (overrides = {}) =>
  fromJS({
    status: ANALYSIS_STATUS.DONE,
    in_queue_before: 0,
    segments_analyzed: 0,
    total_segments: 100,
    total_raw: 1000,
    total_equivalent: 500,
    ...overrides,
  })

const project = fromJS({id: 1, password: 'pw', name: 'My Project'})

beforeEach(() => {
  global.config = {support_mail: 'support@matecat.com', daemon_warning: false}
  downloadAnalysisReport.mockResolvedValue(undefined)
})

test('renders the project name and word count saving', () => {
  render(<AnalyzeHeader data={buildData()} project={project} />)
  expect(screen.getByText('My Project')).toBeInTheDocument()
  expect(screen.getByText('Saving on word count')).toBeInTheDocument()
})

test('falls back to an empty project name when missing', () => {
  render(
    <AnalyzeHeader
      data={buildData()}
      project={fromJS({id: 1, password: 'pw'})}
    />,
  )
  expect(screen.getByRole('heading', {level: 5})).toHaveTextContent('')
})

test('DONE status shows Complete badge and triggers a report download', async () => {
  render(<AnalyzeHeader data={buildData()} project={project} />)
  expect(screen.getByText('Complete')).toBeInTheDocument()

  await userEvent.click(screen.getByText('Download Analysis Report'))
  expect(downloadAnalysisReport).toHaveBeenCalledWith({
    idProject: 1,
    password: 'pw',
  })
})

test('logs an error when the report download fails', async () => {
  const consoleSpy = jest.spyOn(console, 'error').mockImplementation(() => {})
  downloadAnalysisReport.mockRejectedValueOnce(new Error('boom'))
  render(<AnalyzeHeader data={buildData()} project={project} />)

  await userEvent.click(screen.getByText('Download Analysis Report'))
  await screen.findByText('Download Analysis Report')
  expect(consoleSpy).toHaveBeenCalled()
  consoleSpy.mockRestore()
})

test('shows a generic "other projects in queue" message on first render', () => {
  render(
    <AnalyzeHeader
      data={buildData({status: ANALYSIS_STATUS.NEW, in_queue_before: 3})}
      project={project}
    />,
  )
  // previousQueueSizeRef starts at 0, so 0 <= 3 is true on the first render
  expect(
    screen.getByText('There are other projects in queue.'),
  ).toBeInTheDocument()
})

test('shows the "there are still X segments" message once the queue shrinks', () => {
  // AnalyzeHeader is memoized on data.equals(), so each render below must be
  // structurally different (not just re-invoked with an equal Immutable Map)
  // for React to actually re-render the component.
  const {rerender} = render(
    <AnalyzeHeader
      data={buildData({status: ANALYSIS_STATUS.NEW, in_queue_before: 5})}
      project={project}
    />,
  )
  expect(
    screen.getByText('There are other projects in queue.'),
  ).toBeInTheDocument()

  // in_queue_before decreased (previousQueueSizeRef(5) <= current(2) is false)
  rerender(
    <AnalyzeHeader
      data={buildData({status: ANALYSIS_STATUS.NEW, in_queue_before: 2})}
      project={project}
    />,
  )
  expect(screen.getByText(/There are still/)).toBeInTheDocument()
  expect(screen.getByText('2')).toBeInTheDocument()
})

test('shows the daemon error message when daemon_warning is active and status is empty/new', () => {
  global.config = {support_mail: 'support@matecat.com', daemon_warning: true}
  render(
    <AnalyzeHeader
      data={buildData({status: '', in_queue_before: 0})}
      project={project}
    />,
  )
  expect(
    screen.getByText(/The analysis seems not to be running/),
  ).toBeInTheDocument()
  expect(screen.getByText('support@matecat.com')).toBeInTheDocument()
})

test('renders a plain support mail string when it has no @ sign', () => {
  global.config = {support_mail: 'contact-us', daemon_warning: true}
  render(
    <AnalyzeHeader
      data={buildData({status: '', in_queue_before: 0})}
      project={project}
    />,
  )
  expect(screen.getAllByText(/contact-us/).length).toBeGreaterThan(0)
})

test('shows the progress bar while FAST_OK with no queue and progressing segments', () => {
  render(
    <AnalyzeHeader
      data={buildData({
        status: ANALYSIS_STATUS.FAST_OK,
        in_queue_before: 0,
        segments_analyzed: 10,
        total_segments: 100,
      })}
      project={project}
    />,
  )
  expect(screen.getByText(/Searching for TM Matches/)).toBeInTheDocument()
})

test('falls back to the error message after too many renders with no progress', () => {
  const baseData = {
    status: ANALYSIS_STATUS.FAST_OK,
    in_queue_before: 0,
    segments_analyzed: 10,
    total_segments: 100,
  }
  const {rerender} = render(
    <AnalyzeHeader data={buildData(baseData)} project={project} />,
  )

  // AnalyzeHeader is memoized on data.equals(), so each render must supply a
  // structurally different Immutable Map (extra `_tick` field) to force a
  // real re-render while segments_analyzed stays unchanged. Each such render
  // increments the internal "no progress" tail counter until it exceeds 9.
  for (let i = 0; i < 11; i++) {
    rerender(
      <AnalyzeHeader
        data={buildData({...baseData, _tick: i})}
        project={project}
      />,
    )
  }
  expect(
    screen.getByText(/The analysis seems not to be running/),
  ).toBeInTheDocument()
})

test('shows the "not to analyze" contact message', () => {
  render(
    <AnalyzeHeader
      data={buildData({status: ANALYSIS_STATUS.NOT_TO_ANALYZE})}
      project={project}
    />,
  )
  expect(
    screen.getByText(/issues with the analysis of this project/),
  ).toBeInTheDocument()
})

test('shows the empty-file error message', () => {
  render(
    <AnalyzeHeader
      data={buildData({status: ANALYSIS_STATUS.EMPTY})}
      project={project}
    />,
  )
  expect(screen.getByText(/No text to translate/)).toBeInTheDocument()
})

test('falls back to the generic error state for any other status', () => {
  render(
    <AnalyzeHeader
      data={buildData({status: 'SOMETHING_ELSE'})}
      project={project}
    />,
  )
  expect(screen.getByText('Failed')).toBeInTheDocument()
})

test('shows an in-progress indicator on the word count while FAST_OK', () => {
  const {container} = render(
    <AnalyzeHeader
      data={buildData({
        status: ANALYSIS_STATUS.FAST_OK,
        in_queue_before: 0,
        segments_analyzed: 1,
        total_segments: 100,
      })}
      project={project}
    />,
  )
  expect(container.querySelector('.percent.in-progress')).not.toBeNull()
})
