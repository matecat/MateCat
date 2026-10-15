import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {downloadAnalysisReport} from './downloadAnalysisReport'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
}

const downloadedName = async (headers) => {
  mswServer.use(
    http.post(
      config.basepath + 'api/app/download-analysis-report',
      () => new HttpResponse('zip', {headers}),
    ),
  )
  window.URL.createObjectURL = jest.fn(() => 'blob:report')
  window.URL.revokeObjectURL = jest.fn()
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
