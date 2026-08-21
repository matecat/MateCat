import React from 'react'
import {render} from '@testing-library/react'
import Header from './Header'

test('portals children into the page <header>', () => {
  document.body.innerHTML = '<header></header>'
  render(<Header>Portal content</Header>)
  expect(document.querySelector('header')).toHaveTextContent('Portal content')
})
