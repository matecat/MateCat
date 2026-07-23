import React from 'react'
import {render, screen} from '@testing-library/react'
import FatalErrorModal from './FatalErrorModal'

test('renders the provided text', () => {
  render(<FatalErrorModal text="Something went wrong" />)

  expect(screen.getByText('Something went wrong')).toBeInTheDocument()
})
