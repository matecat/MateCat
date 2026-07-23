import {render} from '@testing-library/react'
import {screen} from '@testing-library/dom'
import React from 'react'
import Footer from './Footer'

test('renders the static footer with contact and terms links', () => {
  render(<Footer />)
  expect(screen.getByText('Contact us')).toBeInTheDocument()
  expect(screen.getByText('Terms of service')).toBeInTheDocument()
  expect(screen.getByText('Translate')).toBeInTheDocument()
})
