import React from 'react'
import {render, screen} from '@testing-library/react'
import {BreakdownCard} from './BreakdownCard'

const matches = [
  {type: 'new', raw: 80, equivalent: 80},
  {type: 'tm_100', raw: 20, equivalent: 6},
]

const renderCard = (props = {}) =>
  render(
    <BreakdownCard
      matches={matches}
      payableRates={{NO_MATCH: 100, '100%': 30}}
      workflowType="standard"
      totals={{raw: 100, equivalent: 86}}
      done={true}
      {...props}
    />,
  )

describe('BreakdownCard', () => {
  test('shows the buckets once the analysis is complete', () => {
    renderCard()
    expect(
      screen.getByRole('heading', {name: 'Where the words come from'}),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('table', {name: /Analysis by bucket/}),
    ).toBeInTheDocument()
  })

  test('explains the wait while the analysis runs', () => {
    renderCard({done: false})
    expect(screen.queryByRole('table')).not.toBeInTheDocument()
    expect(
      screen.getByText(
        'The breakdown by bucket appears when the analysis is complete.',
      ),
    ).toBeInTheDocument()
  })

  test('says characters for a character count', () => {
    renderCard({countUnit: 'characters'})
    expect(
      screen.getByRole('heading', {name: 'Where the characters come from'}),
    ).toBeInTheDocument()
  })

  test('a page that does not refresh asks for a reload', () => {
    renderCard({done: false, reloadWhenDone: true})
    expect(
      screen.getByText(
        'The analysis is still running. Reload the page once it is complete to see the breakdown by bucket.',
      ),
    ).toBeInTheDocument()
  })
})
