import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {downloadAnalysisReport} from '../../../api/downloadAnalysisReport'
import {ANALYSIS_PHASE} from '../utils/deriveAnalysisView'
import {getWorkLabel, ProjectHeaderCard} from './ProjectHeaderCard'

jest.mock('../../../api/downloadAnalysisReport', () => ({
  downloadAnalysisReport: jest.fn(() => Promise.resolve()),
}))

const totals = {raw: 320, industry: 275, equivalent: 260, discount: 19}

const renderCard = (props = {}) =>
  render(
    <ProjectHeaderCard
      projectId={20}
      projectName="Analyzed project"
      projectPassword="projpass"
      languageCount={2}
      phase={ANALYSIS_PHASE.DONE}
      totals={totals}
      {...props}
    />,
  )

describe('ProjectHeaderCard', () => {
  beforeEach(() => {
    global.config = {
      ...global.config,
      daemon_warning: false,
      support_mail: 'support@matecat.com',
    }
    jest.clearAllMocks()
  })

  test('shows the muted ID before the project name', () => {
    renderCard()
    const name = screen.getByRole('heading', {name: 'Analyzed project'})
    const id = screen.getByText('ID 20')
    expect(
      id.compareDocumentPosition(name) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy()
  })

  test('shows the language combinations badge only for more than one', () => {
    const {unmount} = renderCard({languageCount: 3})
    expect(screen.getByText('3 language combinations')).toBeInTheDocument()
    unmount()

    renderCard({languageCount: 1})
    expect(screen.queryByText(/language combinations/)).not.toBeInTheDocument()
  })

  test.each([
    [ANALYSIS_PHASE.DONE, 'Complete'],
    [ANALYSIS_PHASE.COUNTING, 'Counting words'],
    [ANALYSIS_PHASE.QUEUE, 'In queue'],
    [ANALYSIS_PHASE.ANALYZING, 'Analyzing'],
  ])('phase %s shows the %s status', (phase, label) => {
    renderCard({phase})
    expect(screen.getByText(label)).toBeInTheDocument()
  })

  test('a stopped analysis daemon shows the failure and the support contact', () => {
    global.config.daemon_warning = true
    renderCard({phase: ANALYSIS_PHASE.QUEUE})
    expect(screen.getByText('Failed')).toBeInTheDocument()
    expect(
      screen.getByRole('link', {name: 'support@matecat.com'}),
    ).toHaveAttribute('href', 'mailto:support@matecat.com')
  })

  test('the analysis report is downloadable once complete', () => {
    const {unmount} = renderCard({phase: ANALYSIS_PHASE.ANALYZING})
    expect(
      screen.queryByRole('button', {name: /Download analysis report/}),
    ).not.toBeInTheDocument()
    unmount()

    renderCard()
    fireEvent.click(
      screen.getByRole('button', {name: /Download analysis report/}),
    )
    expect(downloadAnalysisReport).toHaveBeenCalledWith({
      idProject: 20,
      password: 'projpass',
    })
  })

  test('the saving card shows the server discount and the work label', () => {
    renderCard()
    expect(screen.getByText('19%')).toBeInTheDocument()
    expect(
      screen.getByText('42 work minutes at 3,000 w/day'),
    ).toBeInTheDocument()
  })

  test('while analyzing the saving is computed from the live totals', () => {
    renderCard({
      phase: ANALYSIS_PHASE.ANALYZING,
      totals: {raw: 200, industry: 0, equivalent: 150, discount: 0},
    })
    expect(screen.getByText('25%')).toBeInTheDocument()
  })

  test('the saving is pending before the analysis starts', () => {
    renderCard({phase: ANALYSIS_PHASE.QUEUE})
    expect(screen.getAllByTestId('pending')).toHaveLength(2)
  })
})

describe('getWorkLabel', () => {
  test.each([
    [9000, '3 work days at 3,000 w/day'],
    [3000, '1 work day at 3,000 w/day'],
    [750, '2 work hours at 3,000 w/day'],
    [375, '1 work hour at 3,000 w/day'],
    [100, '16 work minutes at 3,000 w/day'],
    [0, '0 work days at 3,000 w/day'],
  ])('%s weighted words → %s', (words, label) => {
    expect(getWorkLabel(words)).toBe(label)
  })
})
