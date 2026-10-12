import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {copyAllSourceToTarget} from './copyAllSourceToTarget'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  id_job: '77',
  currentPassword: 'jobpwd',
}

const url = config.basepath + 'api/app/copy-all-source-to-target'

test('posts copy-all with job credentials from config and returns data', async () => {
  let form
  mswServer.use(
    http.post(url, async ({request}) => {
      form = await request.formData()
      return HttpResponse.json({code: 1, segments_modified: 5})
    }),
  )

  const response = await copyAllSourceToTarget()

  expect(response).toEqual({code: 1, segments_modified: 5})
  expect(form.get('id_job')).toBe('77')
  expect(form.get('password')).toBe('jobpwd')
  // revision_number is omitted from the body when not provided
  expect(form.get('revision_number')).toBeNull()
})

test('sends the explicit idJob and password when provided', async () => {
  let form
  mswServer.use(
    http.post(url, async ({request}) => {
      form = await request.formData()
      return HttpResponse.json({code: 1, segments_modified: 0})
    }),
  )

  await copyAllSourceToTarget({
    idJob: '999',
    password: 'other',
  })

  expect(form.get('id_job')).toBe('999')
  expect(form.get('password')).toBe('other')
  // the phase is resolved from the password, so no revision number is sent
  expect(form.get('revision_number')).toBeNull()
})

test('rejects with the response on a non-ok status', async () => {
  mswServer.use(http.post(url, () => new HttpResponse(null, {status: 500})))

  const result = await copyAllSourceToTarget().catch((error) => error)

  expect(result.ok).toBe(false)
})

test('rejects with the errors array when the payload carries errors', async () => {
  const errors = [{code: 1, message: 'nope'}]
  mswServer.use(http.post(url, () => HttpResponse.json({errors})))

  const result = await copyAllSourceToTarget().catch((error) => error)

  expect(result).toEqual(errors)
})
