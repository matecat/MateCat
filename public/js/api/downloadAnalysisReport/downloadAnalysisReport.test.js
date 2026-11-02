import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {downloadAnalysisReport} from './downloadAnalysisReport'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: true,
  ajaxDomainsNumber: 4,
}

const url = config.basepath + 'api/app/download-analysis-report'

beforeEach(() => {
  window.URL.createObjectURL = jest.fn(() => 'blob:report')
  window.URL.revokeObjectURL = jest.fn()
})

test('requests the report from the page origin even when multi-domain ajax is on', async () => {
  let received
  mswServer.use(
    http.post(url, async ({request}) => {
      received = new URLSearchParams(await request.text())
      return new HttpResponse('zip-bytes', {
        headers: {
          'Content-Type': 'application/octet-stream',
          'Content-Disposition': 'attachment; filename="Report.zip"',
        },
      })
    }),
  )
  const click = jest
    .spyOn(HTMLAnchorElement.prototype, 'click')
    .mockImplementation(() => {})

  await downloadAnalysisReport({idProject: 395, password: 'projpwd'})

  expect(received.get('id_project')).toBe('395')
  expect(received.get('password')).toBe('projpwd')
  expect(received.get('download_type')).toBe('XTRF')
  expect(click).toHaveBeenCalledTimes(1)
  expect(window.URL.revokeObjectURL).toHaveBeenCalledWith('blob:report')
  click.mockRestore()
})

test('rejects with the response when the server fails', async () => {
  mswServer.use(http.post(url, () => new HttpResponse(null, {status: 500})))

  await expect(
    downloadAnalysisReport({idProject: 395, password: 'projpwd'}),
  ).rejects.toHaveProperty('status', 500)
})

const downloadedName = async (headers) => {
  mswServer.use(http.post(url, () => new HttpResponse('zip', {headers})))
  let name
  const click = jest
    .spyOn(HTMLAnchorElement.prototype, 'click')
    .mockImplementation(function () {
      name = this.download
    })

  await downloadAnalysisReport({idProject: '1', password: 'pwd'})
  click.mockRestore()

  return name
}

test('downloads the report under the original name', async () => {
  expect(
    await downloadedName({
      'Content-Disposition':
        'attachment; filename="____.zip"; filename*=UTF-8\'\'%D0%BF%D1%80%D0%BE%D0%B5%D0%BA%D1%82.zip',
    }),
  ).toBe('проект.zip')
})

test('downloads the report under the default name without a header', async () => {
  expect(await downloadedName({})).toBe('analysis-report.zip')
})
