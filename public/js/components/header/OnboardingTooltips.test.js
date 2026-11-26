import {act, render, screen} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import React from 'react'

import {OnboardingTooltips, ONBOARDING_PAGE} from './OnboardingTooltips'

// The tour points at elements other components render: stand them in with the
// same hook so the real react-joyride has something to anchor to.
const addTarget = (name) => {
  const target = document.createElement('button')
  target.setAttribute('data-onboarding', name)
  document.body.appendChild(target)
}

// react-joyride walks a missing step by warning and moving on: wait for every
// step to be passed over, then let the tour reach its end.
const waitForTourToSkip = async (stepsCount) => {
  await act(async () => {
    for (let attempt = 0; attempt < 50; attempt++) {
      if (console.warn.mock.calls.length >= stepsCount) break
      await new Promise((resolve) => setTimeout(resolve, 10))
    }
    await new Promise((resolve) => setTimeout(resolve, 50))
  })
}

// The tour loads react-joyride lazily. Loading it once here keeps the first
// test from spending its wait on a cold import, which can take over a second in CI.
beforeAll(() => import('react-joyride'))

beforeEach(() => {
  localStorage.clear()
  document.body.innerHTML = ''
  jest.spyOn(console, 'warn').mockImplementation(() => {})
})

afterEach(() => {
  console.warn.mockRestore()
})

test('The home tour points at the user menu and stores the dismissal once closed', async () => {
  addTarget('user-menu')

  render(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)

  expect(
    await screen.findByRole('heading', {name: 'Manage your projects'}),
  ).toBeInTheDocument()
  expect(localStorage.getItem('onBoarding-tooltip-home')).toBeNull()

  await userEvent.click(screen.getAllByRole('button', {name: 'Close'})[0])

  expect(localStorage.getItem('onBoarding-tooltip-home')).toBe('1')
  expect(
    screen.queryByRole('heading', {name: 'Manage your projects'}),
  ).not.toBeInTheDocument()
})

test('The cattool tour points at the actions menu, then at the instructions button', async () => {
  addTarget('cattool-actions-menu')
  addTarget('job-instructions')

  render(<OnboardingTooltips show page={ONBOARDING_PAGE.CATTOOL} />)

  expect(
    await screen.findByRole('heading', {
      name: 'Easier tool navigation and new shortcuts',
    }),
  ).toBeInTheDocument()

  await userEvent.click(screen.getAllByRole('button', {name: 'Close'})[0])

  expect(
    await screen.findByRole('heading', {name: 'Instructions and references'}),
  ).toBeInTheDocument()
  expect(localStorage.getItem('onBoarding-tooltip-cattool')).toBeNull()

  await userEvent.click(screen.getAllByRole('button', {name: 'Close'})[0])

  expect(localStorage.getItem('onBoarding-tooltip-cattool')).toBe('1')
})

test('The cattool tour is stored as seen when only the actions menu is on the page', async () => {
  // A job without instructions or references renders no instructions button.
  addTarget('cattool-actions-menu')

  render(<OnboardingTooltips show page={ONBOARDING_PAGE.CATTOOL} />)

  await userEvent.click(
    (await screen.findAllByRole('button', {name: 'Close'}))[0],
  )
  await waitForTourToSkip(1)

  expect(localStorage.getItem('onBoarding-tooltip-cattool')).toBe('1')
})

test('A tour whose targets are all missing is not stored as seen', async () => {
  render(<OnboardingTooltips show page={ONBOARDING_PAGE.CATTOOL} />)

  await waitForTourToSkip(2)

  expect(console.warn).toHaveBeenCalledWith(
    'Target not mounted',
    expect.anything(),
  )
  expect(localStorage.getItem('onBoarding-tooltip-cattool')).toBeNull()
})

test('Does not render the tour when it was already dismissed', async () => {
  addTarget('user-menu')
  localStorage.setItem('onBoarding-tooltip-home', '1')

  render(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 50))
  })

  expect(
    screen.queryByRole('heading', {name: 'Manage your projects'}),
  ).not.toBeInTheDocument()
})

test('Does not run the tour until show becomes true', async () => {
  addTarget('user-menu')

  const {rerender} = render(
    <OnboardingTooltips show={false} page={ONBOARDING_PAGE.HOME} />,
  )
  await act(async () => {
    await new Promise((resolve) => setTimeout(resolve, 50))
  })

  expect(
    screen.queryByRole('heading', {name: 'Manage your projects'}),
  ).not.toBeInTheDocument()

  rerender(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)

  expect(
    await screen.findByRole('heading', {name: 'Manage your projects'}),
  ).toBeInTheDocument()
})

test('renders the tooltip inside the element OnBoardingTooltip.scss targets', async () => {
  addTarget('user-menu')
  render(<OnboardingTooltips show page={ONBOARDING_PAGE.HOME} />)

  const title = await screen.findByRole('heading', {name: 'Manage your projects'})
  // the stylesheet hides the app-wide aria-label tooltip inside this element
  // eslint-disable-next-line testing-library/no-node-access
  expect(title.closest('.react-joyride__floater')).not.toBeNull()
})
