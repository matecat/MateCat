import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import RevisionCheckbox from './RevisionCheckbox'

describe('RevisionCheckbox', () => {
  test('renders unchecked with price and calls onToggle on click', () => {
    const onToggle = jest.fn()
    render(
      <RevisionCheckbox
        revision={false}
        outsourceConfirmed={false}
        onToggle={onToggle}
        priceCurrencySymbol="€"
        getCurrencyPrice={(price) => price}
        revisionPrice={50}
      />,
    )

    const checkbox = screen.getByRole('checkbox')
    expect(checkbox).not.toBeChecked()
    expect(screen.getByText('+ € 50')).toBeInTheDocument()

    fireEvent.click(checkbox)
    expect(onToggle).toHaveBeenCalledTimes(1)
  })

  test('renders checked and disables the checkbox when outsource is confirmed', () => {
    render(
      <RevisionCheckbox
        revision={true}
        outsourceConfirmed={true}
        onToggle={jest.fn()}
        priceCurrencySymbol="€"
        getCurrencyPrice={(price) => price}
        revisionPrice={50}
      />,
    )

    const checkbox = screen.getByRole('checkbox')
    expect(checkbox).toBeChecked()
    expect(checkbox).toBeDisabled()
    expect(screen.queryByText('+ € 50')).not.toBeInTheDocument()
  })
})
