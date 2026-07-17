import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {HomePageSection} from './HomePageSection'

describe('HomePageSection', () => {
  test('renders the main heading', () => {
    render(<HomePageSection />)
    expect(screen.getByText('Why Choose Us')).toBeInTheDocument()
  })

  test('renders all six content boxes', () => {
    const {container} = render(<HomePageSection />)
    expect(container.querySelectorAll('.content-box').length).toBe(6)
  })

  test('clicking the Benefits button opens the benefits page in a new tab', () => {
    window.open = jest.fn()
    render(<HomePageSection />)
    fireEvent.click(screen.getByRole('button', {name: 'Benefits'}))
    expect(window.open).toHaveBeenCalledWith(
      'https://site.matecat.com/benefits',
      '_blank',
    )
  })
})
