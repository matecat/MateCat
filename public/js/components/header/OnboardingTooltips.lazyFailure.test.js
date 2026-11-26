import React from 'react'
import {render, screen, waitFor} from '@testing-library/react'

import {OnboardingTooltips, ONBOARDING_PAGE} from './OnboardingTooltips'
import {PageErrorBoundary} from '../common/PageErrorBoundary/PageErrorBoundary'
import {rootErrorOptions} from '../../utils/rootErrorOptions'

// The tour's code cannot be loaded, as when its chunk is gone after a deploy
jest.mock('react-joyride', () => {
  throw new Error('Failed to fetch dynamically imported module')
})

test('a tour that cannot be loaded is left out and the page stays', async () => {
  jest.spyOn(console, 'error').mockImplementation(() => {})
  const reports = []
  const onTrackError = (event) => reports.push(event.detail)
  document.addEventListener('track-error', onTrackError)

  const {container} = render(
    <PageErrorBoundary>
      <p>Page content</p>
      <OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />
    </PageErrorBoundary>,
    {onCaughtError: rootErrorOptions.onCaughtError},
  )

  await waitFor(() => expect(reports).toHaveLength(1))
  // still once after React has settled
  await new Promise((resolve) => setTimeout(resolve, 50))
  expect(reports).toHaveLength(1)
  document.removeEventListener('track-error', onTrackError)
  console.error.mockRestore()

  expect(reports[0].message).toEqual(
    new Error('Failed to fetch dynamically imported module'),
  )
  expect(screen.getByText('Page content')).toBeInTheDocument()
  expect(screen.queryByText('Something went wrong')).not.toBeInTheDocument()
  expect(container).toHaveTextContent(/^Page content$/)
})
