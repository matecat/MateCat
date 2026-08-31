import {render} from '@testing-library/react'
import {screen} from '@testing-library/dom'
import React from 'react'
import {fromJS} from 'immutable'
import RevisionFeedback from './RevisionFeedback'

test('renders the revision number and feedback text', () => {
  render(
    <RevisionFeedback
      qualitySummary={fromJS({
        revision_number: 2,
        feedback: 'Great work overall',
      })}
    />,
  )
  expect(screen.getByText('Great work overall')).toBeInTheDocument()
  expect(document.querySelector('.revision-2')).toBeInTheDocument()
})
