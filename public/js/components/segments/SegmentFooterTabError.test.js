import React from 'react'
import {render, screen} from '@testing-library/react'
import {SegmentFooterTabError} from './SegmentFooterTabError'

describe('SegmentFooterTabError', () => {
  test('renders the connection error message and support link', () => {
    render(<SegmentFooterTabError />)

    expect(
      screen.getByText(/unable to provide access to language resources/i),
    ).toBeInTheDocument()

    const link = screen.getByRole('link', {name: /support page/i})
    expect(link).toHaveAttribute(
      'href',
      'https://guides.matecat.com/tm-matches-mt-not-working',
    )
    expect(link).toHaveAttribute('target', '_blank')
  })
})
