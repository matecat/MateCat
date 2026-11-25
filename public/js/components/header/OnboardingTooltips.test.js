import {render, screen, waitFor} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'

import {OnboardingTooltips, ONBOARDING_PAGE} from './OnboardingTooltips'

// The tour runs on the real react-joyride, so its targets must be on the page.
const addTarget = (attributes) => {
  const target = document.createElement('div')
  Object.entries(attributes).forEach(([name, value]) =>
    target.setAttribute(name, value),
  )
  document.body.appendChild(target)
}

// The primary button and the close icon are both labelled "Close": either
// ends the step.
const closeStep = async (user) =>
  user.click(screen.getAllByRole('button', {name: 'Close'})[0])

beforeEach(() => {
  localStorage.clear()
  addTarget({class: 'popover-component-container'})
  addTarget({id: 'action-three-dots'})
  addTarget({id: 'files-instructions'})
})

afterEach(() => {
  document.body.innerHTML = ''
})

test('shows the home tour and remembers it once closed', async () => {
  const user = userEvent.setup()
  render(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)

  expect(await screen.findByText('Manage your projects')).toBeInTheDocument()

  await closeStep(user)

  await waitFor(() =>
    expect(localStorage.getItem('onBoarding-tooltip-home')).toBe('1'),
  )
  expect(screen.queryByText('Manage your projects')).not.toBeInTheDocument()
})

test('does not show a tour that was already closed', async () => {
  localStorage.setItem('onBoarding-tooltip-home', '1')

  render(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)

  // give the lazily loaded library the time it would need to show up
  await new Promise((resolve) => setTimeout(resolve, 50))
  expect(screen.queryByText('Manage your projects')).not.toBeInTheDocument()
})

test('does not start the tour until show becomes true', async () => {
  const {rerender} = render(
    <OnboardingTooltips show={false} page={ONBOARDING_PAGE.HOME} />,
  )
  await new Promise((resolve) => setTimeout(resolve, 50))
  expect(screen.queryByText('Manage your projects')).not.toBeInTheDocument()

  rerender(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)

  expect(await screen.findByText('Manage your projects')).toBeInTheDocument()
})

test('walks the cattool tour step by step and remembers it after the last one', async () => {
  const user = userEvent.setup()
  render(<OnboardingTooltips show page={ONBOARDING_PAGE.CATTOOL} />)

  expect(
    await screen.findByText('Easier tool navigation and new shortcuts'),
  ).toBeInTheDocument()

  await closeStep(user)

  expect(
    await screen.findByText('Instructions and references'),
  ).toBeInTheDocument()
  expect(localStorage.getItem('onBoarding-tooltip-cattool')).toBeNull()

  await closeStep(user)

  await waitFor(() =>
    expect(localStorage.getItem('onBoarding-tooltip-cattool')).toBe('1'),
  )
})

test('renders the tooltip inside the element OnBoardingTooltip.scss targets', async () => {
  render(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)

  const title = await screen.findByText('Manage your projects')
  // the stylesheet hides the app-wide aria-label tooltip inside this element
  // eslint-disable-next-line testing-library/no-node-access
  expect(title.closest('.react-joyride__floater')).not.toBeNull()
})
