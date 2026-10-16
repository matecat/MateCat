import React from 'react'
import {render, screen} from '@testing-library/react'
import {ANALYSIS_PROBLEM, AnalysisProblemCard} from './AnalysisProblemCard'

describe('AnalysisProblemCard', () => {
  beforeEach(() => {
    global.config = {...global.config, support_mail: 'support@matecat.com'}
  })

  test.each([
    [ANALYSIS_PROBLEM.EMPTY, 'There is no text to translate in these files'],
    [ANALYSIS_PROBLEM.NOT_ANALYZED, 'We could not analyze this project'],
    [ANALYSIS_PROBLEM.JOB_NOT_FOUND, 'This job is not in the analysis'],
    [ANALYSIS_PROBLEM.LOAD_ERROR, 'We could not load the analysis'],
  ])('%s explains itself as an alert', (problem, title) => {
    render(<AnalysisProblemCard problem={problem} />)
    expect(screen.getByRole('alert', {name: title})).toBeInTheDocument()
    expect(screen.getByRole('link', {name: 'Contact support'})).toHaveAttribute(
      'href',
      'mailto:support@matecat.com',
    )
  })

  test('a support address that is not an email is shown as text', () => {
    global.config.support_mail = 'your administrator'
    render(<AnalysisProblemCard problem={ANALYSIS_PROBLEM.EMPTY} />)
    expect(screen.queryByRole('link')).not.toBeInTheDocument()
    expect(screen.getByText('your administrator')).toBeInTheDocument()
  })
})
