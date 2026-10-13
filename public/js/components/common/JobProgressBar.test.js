import React from 'react'
import {render, screen} from '@testing-library/react'
import JobProgressBar from './JobProgressBar'

const stats = (overrides = {}) => ({
  raw: {
    total: 10,
    draft: 1,
    new: 1,
    translated: 3,
    approved: 2,
    approved2: 2,
    ...overrides,
  },
})

// the three stacked bar segments are the only <span> elements carrying an inline
// width style; the percentage label uses a data-testid instead.
const getBars = (container) =>
  // eslint-disable-next-line testing-library/no-node-access
  container.querySelectorAll('span[style]')

beforeEach(() => {
  global.config = {isReview: false, revisionNumber: undefined}
})

test('does not render stray comma text nodes between the bar segments', () => {
  const {container} = render(<JobProgressBar stats={stats()} />)

  const bars = getBars(container)
  expect(bars).toHaveLength(3)
  // eslint-disable-next-line testing-library/no-node-access
  expect(bars[0].parentElement).toHaveTextContent('')
})

test('draws each bar with its cumulative width, widest to narrowest', () => {
  const {container} = render(<JobProgressBar stats={stats()} />)

  // translated=3, approved=2, approved2=2, total=10
  const [translatedBar, approvedBar, approved2Bar] = getBars(container)
  expect(translatedBar).toHaveStyle({width: '70%'})
  expect(approvedBar).toHaveStyle({width: '40%'})
  expect(approved2Bar).toHaveStyle({width: '20%'})
})

test('shows the cumulative translated percentage when not in review', () => {
  global.config = {isReview: false}
  render(<JobProgressBar stats={stats()} />)

  expect(screen.getByTestId('progress-bar-amount')).toHaveTextContent('70%')
})

test('shows the approved+approved2 percentage for the first revision', () => {
  global.config = {isReview: true, revisionNumber: 1}
  render(<JobProgressBar stats={stats()} />)

  expect(screen.getByTestId('progress-bar-amount')).toHaveTextContent('40%')
})

test('shows the approved2-only percentage for the second revision', () => {
  global.config = {isReview: true, revisionNumber: 2}
  render(<JobProgressBar stats={stats()} />)

  expect(screen.getByTestId('progress-bar-amount')).toHaveTextContent('20%')
})

test('hides the percentage label when showPercent is false', () => {
  render(<JobProgressBar stats={stats()} showPercent={false} />)

  expect(screen.queryByTestId('progress-bar-amount')).not.toBeInTheDocument()
})
