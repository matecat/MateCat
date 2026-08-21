import React from 'react'
import {render, screen} from '@testing-library/react'
import FatalErrorModal from './FatalErrorModal'

test('renders the text prop', () => {
  render(<FatalErrorModal text="Something broke" />)
  expect(screen.getByText('Something broke')).toBeInTheDocument()
})
