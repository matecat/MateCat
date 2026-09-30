import React from 'react'
import {render, screen} from '@testing-library/react'
import OutsourceLoader from './OutsourceLoader'

describe('OutsourceLoader', () => {
  test('renders the generic message when there is no translators number', () => {
    render(<OutsourceLoader />)
    expect(
      screen.getByText('Choosing the best available translator...'),
    ).toBeInTheDocument()
  })

  test('renders the generic message when translatorsNumber is 30 or below', () => {
    render(<OutsourceLoader translatorsNumber={{asInt: 30, printable: '30'}} />)
    expect(
      screen.getByText('Choosing the best available translator...'),
    ).toBeInTheDocument()
  })

  test('renders the detailed message when translatorsNumber is above 30', () => {
    render(
      <OutsourceLoader translatorsNumber={{asInt: 45, printable: '45+'}} />,
    )
    expect(
      screen.getByText(
        'Choosing the best available translator from the matching 45+...',
      ),
    ).toBeInTheDocument()
  })
})
