import {http, HttpResponse} from 'msw'
import {saveAs} from 'file-saver'
import {mswServer} from '../../../mocks/mswServer'
import {downloadFile} from './downloadFile'

jest.mock('file-saver', () => ({saveAs: jest.fn()}))

global.config = {
  basepath: 'http://localhost/',
  id_job: '77',
  password: 'pwd',
}

const serveDownload = (headers) =>
  mswServer.use(
    http.get(
      config.basepath + 'api/v2/translation/77/pwd',
      () => new HttpResponse('content', {headers}),
    ),
  )

beforeEach(() => saveAs.mockClear())

test('saves the file under the original name', async () => {
  serveDownload({
    'Content-Disposition':
      'attachment; filename="_______.txt"; filename*=UTF-8\'\'%D1%81%D0%BF%D0%B0%D1%81%D0%B8%D0%B1%D0%BE.txt',
  })

  await downloadFile({})

  expect(saveAs).toHaveBeenCalledWith(expect.anything(), 'спасибо.txt')
})

test('saves the file under a fallback name without a header', async () => {
  serveDownload({})

  await downloadFile({})

  expect(saveAs).toHaveBeenCalledWith(expect.anything(), 'download')
})
