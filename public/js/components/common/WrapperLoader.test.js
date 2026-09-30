import React from 'react'
import {render} from '@testing-library/react'
import WrapperLoader from './WrapperLoader'

test('renders overlay and loader elements', () => {
  // decorative positioning divs with no accessible role — no semantic Testing Library
  // query applies, so container access is the only way to assert they render
  const {container} = render(<WrapperLoader />)
  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('.overlayLoader')).toBeInTheDocument()
  // eslint-disable-next-line testing-library/no-container, testing-library/no-node-access
  expect(container.querySelector('.loader')).toBeInTheDocument()
})
