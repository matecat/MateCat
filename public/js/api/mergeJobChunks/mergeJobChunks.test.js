import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {mergeJobChunks} from './index'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
}

const conflict = {
  errors: [
    {
      code: 0,
      message:
        'This job is being changed by another request, try again in a few minutes',
    },
  ],
}

const answer = (body, init) =>
  mswServer.use(
    http.post(config.basepath + 'api/app/split-job-merge', () =>
      HttpResponse.json(body, init),
    ),
  )

test('a 409 rejects with the response and the error list', async () => {
  answer(conflict, {status: 409})

  const rejection = await mergeJobChunks(
    {id: 1, password: 'pp'},
    {id: 2},
  ).catch((error) => error)

  expect(rejection.response.status).toBe(409)
  expect(rejection.errors).toEqual(conflict.errors)
})

test('a non-ok status with an empty body rejects with the response alone', async () => {
  mswServer.use(
    http.post(
      config.basepath + 'api/app/split-job-merge',
      () =>
        new HttpResponse(null, {status: 409, headers: {'Content-Length': '0'}}),
    ),
  )

  const rejection = await mergeJobChunks(
    {id: 1, password: 'pp'},
    {id: 2},
  ).catch((error) => error)

  expect(rejection.response.status).toBe(409)
  expect(rejection.errors).toBeUndefined()
})

test('an error list in a 200 rejects with the error list', async () => {
  answer(conflict)

  const rejection = await mergeJobChunks(
    {id: 1, password: 'pp'},
    {id: 2},
  ).catch((error) => error)

  expect(rejection.errors).toEqual(conflict.errors)
})

test('a 200 without errors resolves with the data', async () => {
  answer({data: {chunks: []}})

  expect(await mergeJobChunks({id: 1, password: 'pp'}, {id: 2})).toEqual({
    data: {chunks: []},
  })
})
