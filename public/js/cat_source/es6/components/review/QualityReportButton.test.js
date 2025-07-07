import {render, screen} from '@testing-library/react'
import {http, HttpResponse} from 'msw'
import React from 'react'
import {mswServer} from '../../../../../mocks/mswServer'

import {QualityReportButton} from './QualityReportButton'

window.config = {
  basepath: 'http://localhost/',
  id_job: '1',
  password: '1',
}

mswServer.use(
  ...[
    http.get(config.basepath + 'api/app/jobs/1/1/quality-report', () => {
      return HttpResponse.json({})
    }),
  ],
)

test('works properly', () => {
  render(<QualityReportButton />)

  expect(screen.getByTestId('report-button')).toBeVisible()
})
