import React from 'react'
import {render, screen} from '@testing-library/react'
import SuccessModal from './SuccessModal'

test('renders the provided text', () => {
  render(<SuccessModal text="All good" />)

  expect(screen.getByText('All good')).toBeInTheDocument()
})
