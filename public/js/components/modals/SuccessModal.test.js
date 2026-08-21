import React from 'react'
import {render, screen} from '@testing-library/react'
import SuccessModal from './SuccessModal'

test('renders the text prop', () => {
  render(<SuccessModal text="All good" />)
  expect(screen.getByText('All good')).toBeInTheDocument()
})
