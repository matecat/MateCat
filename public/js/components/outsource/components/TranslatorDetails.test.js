import React from 'react'
import {render, screen} from '@testing-library/react'
import {fromJS} from 'immutable'
import TranslatorDetails from './TranslatorDetails'

const baseJob = fromJS({source: 'en-US', target: 'it-IT'})

describe('TranslatorDetails', () => {
  test('renders translator info when a translator name is present', () => {
    const chunkQuote = fromJS({
      t_name: 'Jane Doe',
      t_experience_years: 7,
      words: 1200,
      price: 100,
    })
    render(
      <TranslatorDetails
        chunkQuote={chunkQuote}
        translatedWords="1,000"
        job={baseJob}
        outsourceConfirmed={false}
        priceCurrencySymbol="€"
        getCurrencyPrice={(price) => price}
      />,
    )

    expect(
      screen.getByText('Best identified translator for this job:'),
    ).toBeInTheDocument()
    expect(screen.getByText('Jane Doe')).toBeInTheDocument()
    expect(screen.getByText('by Translated')).toBeInTheDocument()
    expect(
      screen.getByText('1,000 words translated last 12 months'),
    ).toBeInTheDocument()
    expect(screen.getByText('7 years of experience')).toBeInTheDocument()
    expect(screen.getByText('1,200 words')).toBeInTheDocument()
    expect(screen.getByText('€ 100')).toBeInTheDocument()
  })

  test('renders fallback message when no translator name is found', () => {
    const chunkQuote = fromJS({t_name: '', words: 500, price: 20})
    render(
      <TranslatorDetails
        chunkQuote={chunkQuote}
        translatedWords="500"
        job={baseJob}
        outsourceConfirmed={false}
        priceCurrencySymbol="€"
        getCurrencyPrice={(price) => price}
      />,
    )

    expect(
      screen.queryByText('Best identified translator for this job:'),
    ).not.toBeInTheDocument()
    expect(screen.getByText(/most qualified translator/)).toBeInTheDocument()
  })

  test('hides the price line when the outsource is already confirmed', () => {
    const chunkQuote = fromJS({t_name: '', words: 500, price: 20})
    render(
      <TranslatorDetails
        chunkQuote={chunkQuote}
        translatedWords="500"
        job={baseJob}
        outsourceConfirmed={true}
        priceCurrencySymbol="€"
        getCurrencyPrice={(price) => price}
      />,
    )

    expect(screen.queryByText('€ 20')).not.toBeInTheDocument()
  })
})
