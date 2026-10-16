import React from 'react'
import {render, screen} from '@testing-library/react'
import {AnalysisNoticeContext, AnalysisPage} from './AnalysisPage'

test('renders the page heading and its content', () => {
  render(
    <AnalysisPage title="Job analysis">
      <p>Content</p>
    </AnalysisPage>,
  )
  expect(
    screen.getByRole('heading', {level: 1, name: 'Job analysis'}),
  ).toBeInTheDocument()
  expect(screen.getByText('Content')).toBeInTheDocument()
})

test('shows the page notice under the heading', () => {
  render(
    <AnalysisNoticeContext.Provider value="Reload the page">
      <AnalysisPage title="Job analysis" />
    </AnalysisNoticeContext.Provider>,
  )
  expect(screen.getByRole('status')).toHaveTextContent('Reload the page')
})

test('no notice by default', () => {
  render(<AnalysisPage title="Job analysis" />)
  expect(screen.queryByRole('status')).not.toBeInTheDocument()
})
