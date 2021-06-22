import {render, screen} from '@testing-library/react'
import React from 'react'

import {QualityReportButton} from './QualityReportButton'

test('works properly', () => {
  render(<QualityReportButton />)

  expect(screen.getByTestId('report-button')).toBeVisible()
})
