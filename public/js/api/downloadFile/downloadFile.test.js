import {downloadFromUrl} from '../downloadFromUrl'
import {downloadFile} from './downloadFile'

jest.mock('../downloadFromUrl', () => ({downloadFromUrl: jest.fn()}))

beforeEach(() => {
  downloadFromUrl.mockReset()
  global.config = {
    ...global.config,
    basepath: 'http://localhost/',
    id_job: '9',
    password: 'jobpwd',
  }
})

test('downloads the translation of the given job with the error check on', async () => {
  const onProgress = jest.fn()
  const {signal} = new AbortController()

  await downloadFile({idJob: 7, password: 'pass', onProgress, signal})

  expect(downloadFromUrl).toHaveBeenCalledWith({
    url: 'http://localhost/api/v2/translation/7/pass?download_type=all&disableErrorCheck=0',
    onProgress,
    signal,
  })
})

test('turns the error check off when asked to skip it', async () => {
  await downloadFile({idJob: 7, password: 'pass', checkErrors: false})

  expect(downloadFromUrl.mock.calls[0][0].url).toContain('disableErrorCheck=1')
})

test('defaults to the job open in the page', async () => {
  await downloadFile({})

  expect(downloadFromUrl.mock.calls[0][0].url).toBe(
    'http://localhost/api/v2/translation/9/jobpwd?download_type=all&disableErrorCheck=0',
  )
})
