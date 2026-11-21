import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {getQualityReportSegmentsFiles} from './getQualityReportSegmentsFiles'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  id_job: '72',
  password: 'jobpwd',
}

const url = config.basepath + 'api/app/jobs/72/jobpwd/quality-report/segments'

const captureQuery = () => {
  const captured = {}
  mswServer.use(
    http.get(url, ({request}) => {
      captured.search = new URL(request.url).search
      captured.params = new URL(request.url).searchParams
      return HttpResponse.json({segments: [], _links: {}})
    }),
  )
  return captured
}

test('sends each list item as its own key[] pair', async () => {
  const captured = captureQuery()

  await getQualityReportSegmentsFiles(
    {severity: ['Minor', 'Très grave'], issue_category: ['316', '319']},
    0,
  )

  expect(captured.params.getAll('filter[severity][]')).toEqual([
    'Minor',
    'Très grave',
  ])
  expect(captured.params.getAll('filter[issue_category][]')).toEqual([
    '316',
    '319',
  ])
  // The old form joined the list into one "a,b" value
  expect(captured.params.has('filter[severity]')).toBe(false)
  expect(captured.search).not.toContain('%2C')
})

test('keeps scalar filters as single key=value pairs', async () => {
  const captured = captureQuery()

  await getQualityReportSegmentsFiles(
    {status: 'APPROVED', revision_number: 1, id_segment: '7501'},
    0,
  )

  expect(captured.params.get('filter[status]')).toBe('APPROVED')
  expect(captured.params.get('filter[revision_number]')).toBe('1')
  expect(captured.params.get('filter[id_segment]')).toBe('7501')
})

test('skips empty lists, empty items and null filters', async () => {
  const captured = captureQuery()

  await getQualityReportSegmentsFiles(
    {severity: [], issue_category: ['', null, '316'], status: null},
    0,
  )

  expect(captured.params.has('filter[severity][]')).toBe(false)
  expect(captured.params.getAll('filter[issue_category][]')).toEqual(['316'])
  expect(captured.params.has('filter[status]')).toBe(false)
})

test('passes ref_segment through', async () => {
  const captured = captureQuery()

  await getQualityReportSegmentsFiles({severity: ['Major']}, 7500)

  expect(captured.params.get('ref_segment')).toBe('7500')
})
