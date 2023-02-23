import {render, screen} from '@testing-library/react'
import {rest} from 'msw'
import React from 'react'
import {mswServer} from '../../../../../mocks/mswServer'

import {QualityReportButton} from './QualityReportButton'

window.config = {
  basepath: '/',
  id_job: '1',
  password: '1',
}

const executeMswServer = () => {
  mswServer.use(
    ...[
      rest.get('/api/app/jobs/1/1/quality-report', (req, res, ctx) => {
        return res(ctx.status(200), ctx.json({}))
      }),
    ],
  )
}

beforeEach(() => {
  executeMswServer()
})

test('works properly', () => {
  render(<QualityReportButton />)

  expect(screen.getByTestId('report-button')).toBeVisible()
})
