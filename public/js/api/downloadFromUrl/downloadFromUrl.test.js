import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {saveBlob} from '../../utils/saveBlob'
import {
  downloadFromUrl,
  getFilenameFromContentDisposition,
} from './downloadFromUrl'

jest.mock('../../utils/saveBlob', () => ({saveBlob: jest.fn()}))

const URL_TO_DOWNLOAD = 'http://localhost/api/v2/tmx/7/pass'

beforeEach(() => {
  saveBlob.mockClear()
})

describe('getFilenameFromContentDisposition', () => {
  test('reads a quoted filename', () => {
    expect(
      getFilenameFromContentDisposition('attachment; filename="job_12.zip"'),
    ).toBe('job_12.zip')
  })

  test('keeps a semicolon inside a quoted filename', () => {
    expect(
      getFilenameFromContentDisposition('attachment; filename="a;b.docx"'),
    ).toBe('a;b.docx')
  })

  test('reads an unquoted filename', () => {
    expect(
      getFilenameFromContentDisposition('attachment; filename=report.xlsx'),
    ).toBe('report.xlsx')
  })

  test('prefers the encoded filename* over the plain one', () => {
    expect(
      getFilenameFromContentDisposition(
        'attachment; filename="fallback.zip"; filename*=UTF-8\'\'%E6%97%A5%E6%9C%AC.zip',
      ),
    ).toBe('日本.zip')
  })

  test('falls back to the plain filename when filename* is malformed', () => {
    expect(
      getFilenameFromContentDisposition(
        'attachment; filename*=UTF-8\'\'%E6%97; filename="fallback.zip"',
      ),
    ).toBe('fallback.zip')
  })

  test('returns undefined without a header or a filename', () => {
    expect(getFilenameFromContentDisposition(null)).toBeUndefined()
    expect(getFilenameFromContentDisposition('attachment')).toBeUndefined()
  })
})

describe('downloadFromUrl', () => {
  test('saves the response body under the filename sent by the server', async () => {
    mswServer.use(
      http.get(URL_TO_DOWNLOAD, () =>
        HttpResponse.text('tmx-content', {
          headers: {'Content-Disposition': 'attachment; filename="job_7.tmx"'},
        }),
      ),
    )

    await downloadFromUrl({url: URL_TO_DOWNLOAD})

    const [blob, filename] = saveBlob.mock.calls[0]
    expect(filename).toBe('job_7.tmx')
    expect(await blob.text()).toBe('tmx-content')
  })

  test('reports how much of the file has arrived against its size', async () => {
    mswServer.use(
      http.get(URL_TO_DOWNLOAD, () =>
        HttpResponse.text('0123456789', {
          headers: {
            'Content-Length': '10',
            'Content-Disposition': 'attachment; filename="a.zip"',
          },
        }),
      ),
    )
    const onProgress = jest.fn()

    await downloadFromUrl({url: URL_TO_DOWNLOAD, onProgress})

    expect(onProgress).toHaveBeenCalledWith({loaded: 0, total: 10})
    expect(onProgress).toHaveBeenLastCalledWith({loaded: 10, total: 10})
    expect(await saveBlob.mock.calls[0][0].text()).toBe('0123456789')
  })

  test('reports an unknown size when the server sends no Content-Length', async () => {
    // A streamed body carries no Content-Length, like the job TMX export
    const body = new ReadableStream({
      start(controller) {
        controller.enqueue(new TextEncoder().encode('abc'))
        controller.close()
      },
    })
    mswServer.use(http.get(URL_TO_DOWNLOAD, () => new HttpResponse(body)))
    const onProgress = jest.fn()

    await downloadFromUrl({url: URL_TO_DOWNLOAD, onProgress})

    expect(onProgress).toHaveBeenLastCalledWith({loaded: 3, total: 0})
  })

  test('rejects and saves nothing when the server answers with an error', async () => {
    mswServer.use(
      http.get(URL_TO_DOWNLOAD, () => new HttpResponse(null, {status: 500})),
    )

    await expect(downloadFromUrl({url: URL_TO_DOWNLOAD})).rejects.toEqual(
      expect.objectContaining({status: 500}),
    )
    expect(saveBlob).not.toHaveBeenCalled()
  })

  test('rejects and saves nothing when the download is aborted', async () => {
    mswServer.use(http.get(URL_TO_DOWNLOAD, () => HttpResponse.text('x')))
    const controller = new AbortController()
    controller.abort()

    await expect(
      downloadFromUrl({url: URL_TO_DOWNLOAD, signal: controller.signal}),
    ).rejects.toThrow()
    expect(saveBlob).not.toHaveBeenCalled()
  })
})
