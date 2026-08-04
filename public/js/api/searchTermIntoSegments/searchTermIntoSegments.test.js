import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {searchTermIntoSegments} from './searchTermIntoSegments'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  id_job: '77',
  currentPassword: 'jobpwd',
  revisionNumber: undefined,
}

const url = config.basepath + 'api/app/search'

test('posts search with job credentials from config and returns data', async () => {
  let form
  mswServer.use(
    http.post(url, async ({request}) => {
      form = await request.formData()
      return HttpResponse.json({total: 2})
    }),
  )

  const response = await searchTermIntoSegments({
    token: 'tok',
    source: 'hello',
    target: 'ciao',
    status: 'all',
    matchcase: false,
    exactmatch: false,
    inCurrentChunkOnly: true,
    replace: 'world',
    revisionNumber: 2,
  })

  expect(response).toEqual({total: 2})
  expect(form.get('id_job')).toBe('77')
  expect(form.get('password')).toBe('jobpwd')
  expect(form.get('source')).toBe('hello')
  expect(form.get('target')).toBe('ciao')
  expect(form.get('replace')).toBe('world')
  expect(form.get('status')).toBe('all')
  expect(form.get('inCurrentChunkOnly')).toBe('true')
  expect(form.get('revision_number')).toBe('2')
})

test('prefers explicit idJob and password over config defaults', async () => {
  let form
  mswServer.use(
    http.post(url, async ({request}) => {
      form = await request.formData()
      return HttpResponse.json({total: 0})
    }),
  )

  await searchTermIntoSegments({idJob: '999', password: 'other', source: 'x'})

  expect(form.get('id_job')).toBe('999')
  expect(form.get('password')).toBe('other')
})

test('rejects with the response on a non-ok status', async () => {
  mswServer.use(http.post(url, () => new HttpResponse(null, {status: 500})))

  const result = await searchTermIntoSegments({source: 'x'}).catch(
    (error) => error,
  )

  expect(result.ok).toBe(false)
})

test('rejects with the errors array when the payload carries errors', async () => {
  const errors = [{code: 1, message: 'nope'}]
  mswServer.use(http.post(url, () => HttpResponse.json({errors})))

  const result = await searchTermIntoSegments({source: 'x'}).catch(
    (error) => error,
  )

  expect(result).toEqual(errors)
})
