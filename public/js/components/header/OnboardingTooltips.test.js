import {render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'

import {OnboardingTooltips, ONBOARDING_PAGE} from './OnboardingTooltips'

jest.mock('react-joyride', () => {
  const reactLib = require('react')

  return {
    __esModule: true,
    default: ({callback, run}) =>
      reactLib.createElement(
        'div',
        {'data-testid': 'joyride-mock', 'data-run': run ? 'true' : 'false'},
        reactLib.createElement(
          'button',
          {
            'data-testid': 'joyride-finish',
            onClick: () =>
              callback({status: 'finished', type: 'tour:end', index: 0}),
          },
          'finish',
        ),
      ),
    STATUS: {FINISHED: 'finished', SKIPPED: 'skipped'},
  }
})

beforeEach(() => {
  localStorage.clear()
})

test('Renders the tour when it has not been dismissed yet', () => {
  render(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)

  expect(screen.getByTestId('joyride-mock')).toBeInTheDocument()
})

test('Does not render the tour when it was already dismissed', () => {
  localStorage.setItem('onBoarding-tooltip-home', '1')

  render(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)

  expect(screen.queryByTestId('joyride-mock')).not.toBeInTheDocument()
})

test('Does not run the tour until show becomes true', () => {
  const {rerender} = render(
    <OnboardingTooltips show={false} page={ONBOARDING_PAGE.HOME} />,
  )

  expect(screen.getByTestId('joyride-mock')).toHaveAttribute(
    'data-run',
    'false',
  )

  rerender(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)

  expect(screen.getByTestId('joyride-mock')).toHaveAttribute('data-run', 'true')
})

test('Finishing the tour stores the dismissal and hides the tour', async () => {
  render(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)

  await userEvent.click(screen.getByTestId('joyride-finish'))

  expect(localStorage.getItem('onBoarding-tooltip-home')).toBe('1')
  expect(screen.queryByTestId('joyride-mock')).not.toBeInTheDocument()
})

test('Renders the cattool tour page', () => {
  render(<OnboardingTooltips show page={ONBOARDING_PAGE.CATTOOL} />)

  expect(screen.getByTestId('joyride-mock')).toBeInTheDocument()
})
