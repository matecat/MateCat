import {render, screen} from '@testing-library/react'
import React from 'react'

import {BulkSelectionBar} from './BulkSelectionBar'

test('renders properly', () => {
  global.config = {}

  render(<BulkSelectionBar isReview />)

  screen.debug()
})
