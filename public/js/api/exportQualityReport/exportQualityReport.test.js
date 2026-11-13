import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {exportQualityReport} from './exportQualityReport'

global.config = {
  id_job: '77',
  password: 'pwd',
}

const serveExport = (headers) =>
  mswServer.use(
    http.post(
      window.origin + '/api/v3/qr/download',
      () => new HttpResponse('csv', {headers}),
    ),
  )

test('returns the original name of the export', async () => {
  serveExport({
    'Content-Disposition':
      'attachment; filename="____-77.csv"; filename*=UTF-8\'\'%D0%BE%D1%82%D1%87-77.csv',
  })

  const {filename} = await exportQualityReport()

  expect(filename).toBe('отч-77.csv')
})

test('returns a default name without a header', async () => {
  serveExport({})

  const {filename} = await exportQualityReport({format: 'json'})

  expect(filename).toBe('quality-report.json')
})
