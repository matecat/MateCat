import {render, screen, waitFor} from '@testing-library/react'
import React from 'react'

import {BulkSelectionBar} from './BulkSelectionBar'

test('renders properly', async () => {
  global.config = {}

  render(<BulkSelectionBar />)

  await waitFor(() => {
    expect(screen.getByText('back')).toBeVisible()
  })
})
