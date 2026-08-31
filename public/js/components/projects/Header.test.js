import React from 'react'
import {render} from '@testing-library/react'
import DasboardHeader from './Header'

test('renders children into the header element via a portal', () => {
  const header = document.createElement('header')
  document.body.appendChild(header)

  render(
    <DasboardHeader>
      <span>Header content</span>
    </DasboardHeader>,
  )

  expect(header.textContent).toBe('Header content')

  document.body.removeChild(header)
})
