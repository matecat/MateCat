import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {setTranslation} from './setTranslation'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
}

const url = config.basepath + 'api/app/set-translation'

const answer = (resolver) => mswServer.use(http.post(url, resolver))

// The save queue tells a lost connection from a server failure by the response on the rejection:
// every answer the server gives must carry it.
describe('setTranslation', () => {
  test('resolves with the data of a successful save', async () => {
    answer(() => HttpResponse.json({data: 'OK', propagation: null}))

    await expect(setTranslation({id_segment: 1})).resolves.toEqual({
      data: 'OK',
      propagation: null,
    })
  })

  test('rejects a refusal with the server errors and the response', async () => {
    const errors = [{code: -5, message: 'disabled'}]
    answer(() => HttpResponse.json({errors}))

    const rejection = await setTranslation({id_segment: 1}).catch((e) => e)

    expect(rejection.errors).toEqual(errors)
    expect(rejection.response.status).toBe(200)
  })

  test('rejects an error page that is not JSON with the response', async () => {
    answer(() => new HttpResponse('<html>Bad gateway</html>', {status: 502}))

    const rejection = await setTranslation({id_segment: 1}).catch((e) => e)

    expect(rejection.response.status).toBe(502)
  })

  test('rejects a successful status with an unreadable body with the response', async () => {
    answer(() => new HttpResponse('not json', {status: 200}))

    const rejection = await setTranslation({id_segment: 1}).catch((e) => e)

    expect(rejection.response.status).toBe(200)
  })

  test('rejects a lost connection without a response', async () => {
    answer(() => HttpResponse.error())

    const rejection = await setTranslation({id_segment: 1}).catch((e) => e)

    expect(rejection.response).toBeUndefined()
    expect(rejection.errors).toBeUndefined()
  })
})
