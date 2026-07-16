import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {aiFeedback} from './aiFeedback'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  id_job: '77',
  password: 'jobpwd',
  source_code: 'en-US',
  target_code: 'it-IT',
  id_client: 'client-1',
}

const url = config.basepath + 'api/app/ai-assistant/feedback'

test('posts feedback with job credentials from config and returns data', async () => {
  let received
  mswServer.use(
    http.post(url, async ({request}) => {
      received = await request.json()
      return HttpResponse.json({channel: 'ok'})
    }),
  )

  const response = await aiFeedback({
    idSegment: '5',
    source: 'hello',
    target: 'ciao',
    style: 'faithful',
  })

  expect(response).toEqual({channel: 'ok'})
  expect(received.id_job).toBe('77')
  expect(received.password).toBe('jobpwd')
  expect(received.source_language).toBe('en-US')
  expect(received.target_language).toBe('it-IT')
  expect(received.id_client).toBe('client-1')
  expect(received.id_segment).toBe('5')
  expect(received.text).toBe('hello')
  expect(received.translation).toBe('ciao')
  expect(received.style).toBe('faithful')
})

test('prefers explicit arguments over config defaults', async () => {
  let received
  mswServer.use(
    http.post(url, async ({request}) => {
      received = await request.json()
      return HttpResponse.json({channel: 'ok'})
    }),
  )

  await aiFeedback({
    idJob: '999',
    password: 'other',
    idSegment: '5',
  })

  expect(received.id_job).toBe('999')
  expect(received.password).toBe('other')
})

test('rejects with the response on a non-ok status', async () => {
  mswServer.use(http.post(url, () => new HttpResponse(null, {status: 500})))

  const result = await aiFeedback({idSegment: '5'}).catch((error) => error)

  expect(result.ok).toBe(false)
})

test('rejects with the errors array when the payload carries errors', async () => {
  const errors = [{code: 1, message: 'nope'}]
  mswServer.use(http.post(url, () => HttpResponse.json({errors})))

  const result = await aiFeedback({idSegment: '5'}).catch((error) => error)

  expect(result).toEqual(errors)
})
