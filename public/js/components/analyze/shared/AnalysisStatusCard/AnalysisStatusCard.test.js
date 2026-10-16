import React from 'react'
import {render, screen} from '@testing-library/react'
import {AnalysisStatusCard} from './AnalysisStatusCard'
import {ANALYSIS_PHASE} from '../../utils/deriveAnalysisView'

jest.mock('./AnalysisStatusCard.module.scss', () => ({
  __esModule: true,
  default: {current: 'current', done: 'done'},
}))

const progress = {
  segmentsAnalyzed: 1200,
  totalSegments: 4800,
  percent: 25,
  inQueueBefore: 0,
}

const currentStep = () =>
  screen
    .getAllByRole('listitem')
    .find((item) => item.getAttribute('aria-current') === 'step')

test('counting shows the fast word count message', () => {
  render(
    <AnalysisStatusCard phase={ANALYSIS_PHASE.COUNTING} progress={progress} />,
  )
  expect(screen.getByRole('status')).toHaveTextContent(
    'Counting the words in your files',
  )
  expect(currentStep()).toHaveTextContent('Word count')
  expect(screen.queryByRole('progressbar')).not.toBeInTheDocument()
})

test('queue shows the projects and segments in queue', () => {
  render(
    <AnalysisStatusCard
      phase={ANALYSIS_PHASE.QUEUE}
      progress={{...progress, inQueueBefore: 1500}}
    />,
  )
  expect(screen.getByRole('status')).toHaveTextContent(
    'There are other projects in queue',
  )
  expect(screen.getByText('1,500 segments in queue')).toBeInTheDocument()
  expect(currentStep()).toHaveTextContent('Queue')
  // A done step reads as done, not by its colour alone
  expect(screen.getByText('✓ Word count')).toBeInTheDocument()
})

test('queue with one segment uses the singular', () => {
  render(
    <AnalysisStatusCard
      phase={ANALYSIS_PHASE.QUEUE}
      progress={{...progress, inQueueBefore: 1}}
    />,
  )
  expect(screen.getByText('1 segment in queue')).toBeInTheDocument()
})

test('analyzing shows the progress with its aria values', () => {
  render(
    <AnalysisStatusCard phase={ANALYSIS_PHASE.ANALYZING} progress={progress} />,
  )
  expect(screen.getByRole('status')).toHaveTextContent(
    'Searching for TM matches',
  )
  expect(screen.getByText('1,200 of 4,800 segments · 25%')).toBeInTheDocument()
  const bar = screen.getByRole('progressbar', {name: 'Segments analyzed'})
  expect(bar).toHaveValue(25)
  expect(bar).toHaveAttribute('aria-valuemin', '0')
  expect(bar).toHaveAttribute('aria-valuemax', '100')
  expect(currentStep()).toHaveTextContent('Analysis')
  expect(screen.getByText('Complete')).not.toHaveClass('done')
})

test.each([
  ANALYSIS_PHASE.DONE,
  ANALYSIS_PHASE.NOT_ANALYZED,
  ANALYSIS_PHASE.EMPTY,
])('renders nothing when the phase is %p', (phase) => {
  const {container} = render(
    <AnalysisStatusCard phase={phase} progress={progress} />,
  )
  expect(container).toBeEmptyDOMElement()
})

test('the steps before the current one are done, the later ones next', () => {
  render(
    <AnalysisStatusCard
      phase="analyzing"
      progress={{segmentsAnalyzed: 1, totalSegments: 4, percent: 25}}
    />,
  )
  expect(screen.getByText('Analysis')).toHaveAttribute('aria-current', 'step')
  expect(screen.getByText('1 of 4 segments · 25%')).toBeInTheDocument()
  // Steps before the current one carry the check, later ones do not
  expect(screen.getByText('✓ Queue')).toBeInTheDocument()
  expect(screen.getByText('Complete')).toBeInTheDocument()
})
