import React from 'react'
import {render, screen} from '@testing-library/react'
import {
  buildVolumeAnalysis,
  projectMock,
} from '../../../../mocks/volumeAnalysisMock'
import {ANALYSIS_PHASE, deriveAnalysisView} from '../utils/deriveAnalysisView'
import {ANALYSIS_PROBLEM} from '../AnalysisProblemCard'
import {EmptyAnalysis, getAnalysisProblem} from './EmptyAnalysis'

const viewFor = (summary, jobs) =>
  deriveAnalysisView({analysis: buildVolumeAnalysis({summary, jobs})})

describe('EmptyAnalysis', () => {
  beforeEach(() => {
    global.config = {...global.config, support_mail: 'support@matecat.com'}
  })

  test('no text found: the project header stays, with no saving', () => {
    render(
      <EmptyAnalysis
        project={projectMock}
        view={viewFor({status: 'EMPTY'}, [])}
      />,
    )
    expect(
      screen.getByRole('heading', {level: 1, name: 'Project analysis'}),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('heading', {name: 'Analyzed project'}),
    ).toBeInTheDocument()
    expect(screen.getByText('No text found')).toBeInTheDocument()
    expect(screen.getByText('No analysis available')).toBeInTheDocument()
    expect(
      screen.getByRole('alert', {
        name: 'There is no text to translate in these files',
      }),
    ).toBeInTheDocument()
  })

  test('an analysis that failed', () => {
    render(
      <EmptyAnalysis
        project={projectMock}
        view={viewFor({status: 'NOT_TO_ANALYZE'})}
      />,
    )
    expect(screen.getByText('Not analyzed')).toBeInTheDocument()
    expect(
      screen.getByRole('alert', {name: 'We could not analyze this project'}),
    ).toBeInTheDocument()
  })

  test('a job page whose chunk is gone', () => {
    render(
      <EmptyAnalysis
        project={projectMock}
        view={deriveAnalysisView({
          analysis: buildVolumeAnalysis({jobs: []}),
          jobAnalysis: true,
        })}
        jobAnalysis={true}
      />,
    )
    expect(
      screen.getByRole('heading', {level: 1, name: 'Job analysis'}),
    ).toBeInTheDocument()
    // The translator gets no project header
    expect(
      screen.queryByRole('heading', {name: 'Analyzed project'}),
    ).not.toBeInTheDocument()
    expect(
      screen.getByRole('alert', {name: 'This job is not in the analysis'}),
    ).toBeInTheDocument()
  })

  test('getAnalysisProblem', () => {
    expect(getAnalysisProblem(ANALYSIS_PHASE.NOT_ANALYZED)).toBe(
      ANALYSIS_PROBLEM.NOT_ANALYZED,
    )
    expect(getAnalysisProblem(ANALYSIS_PHASE.EMPTY, true)).toBe(
      ANALYSIS_PROBLEM.EMPTY,
    )
    expect(getAnalysisProblem(ANALYSIS_PHASE.DONE, true)).toBe(
      ANALYSIS_PROBLEM.JOB_NOT_FOUND,
    )
    // A project page with no job to list
    expect(getAnalysisProblem(ANALYSIS_PHASE.DONE)).toBe(ANALYSIS_PROBLEM.EMPTY)
  })
})
