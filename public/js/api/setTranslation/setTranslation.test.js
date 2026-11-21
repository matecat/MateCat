import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {setTranslation} from './setTranslation'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
}

const url = config.basepath + 'api/app/set-translation'

const receiveRequest = () => {
  const received = {}
  mswServer.use(
    http.post(url, async ({request}) => {
      Object.assign(received, Object.fromEntries(await request.formData()))
      return HttpResponse.json({data: 'OK'})
    }),
  )
  return received
}

test('sends typed tag-like text unencoded and the real tags as they are, as the backend expects for now', async () => {
  const received = receiveRequest()

  await setTranslation({
    id_segment: '5',
    translation: 'A &lt;g&gt; b <g id="1">c</g> &lt;b&gt;',
  })

  expect(received.id_segment).toBe('5')
  expect(received.translation).toBe('A <g> b <g id="1">c</g> &lt;b&gt;')
})

test('leaves out the fields that have no value', async () => {
  const received = receiveRequest()

  await setTranslation({id_segment: '5', translation: 'a', propagate: null})

  expect(received).not.toHaveProperty('propagate')
})

test('rejects with the errors the server returns', async () => {
  mswServer.use(
    http.post(url, () =>
      HttpResponse.json({errors: [{code: -1, message: 'error'}]}),
    ),
  )

  await expect(
    setTranslation({id_segment: '5', translation: 'a'}),
  ).rejects.toEqual({errors: [{code: -1, message: 'error'}]})
})

test('rejects with the response and its errors when the request fails', async () => {
  mswServer.use(
    http.post(url, () =>
      HttpResponse.json(
        {errors: [{code: -10, message: 'error'}]},
        {status: 500},
      ),
    ),
  )

  await expect(
    setTranslation({id_segment: '5', translation: 'a'}),
  ).rejects.toMatchObject({
    response: {status: 500},
    errors: [{code: -10, message: 'error'}],
  })
})
