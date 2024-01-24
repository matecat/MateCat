import {http, HttpResponse} from 'msw'

import {getProjects} from '.'
import {mswServer} from '../../../../../mocks/mswServer'
global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  ajaxDomainsNumber: 20,
}
test('works properly with empty filter', async () => {
  const payload = {errors: [], data: {fake: 'data'}}

  mswServer.use(
    http.post(config.basepath, () => {
      return HttpResponse.json(payload)
    }),
  )

  const data = await getProjects({searchFilter: {}, team: {}})

  expect(data).toEqual({data: payload.data})
})

test('works properly with full filter', async () => {
  const payload = {errors: [], data: {fake: 'data'}}

  mswServer.use(
    http.post(config.basepath, () => {
      return HttpResponse.json(payload)
    }),
  )

  const data = await getProjects({
    searchFilter: {filter: {foo: 'bar'}},
    team: {},
  })

  expect(data).toEqual({data: payload.data})
})

test('throws on non empty errors', async () => {
  expect.assertions(1)
  const payload = {errors: [500, 'VERY_BAD_ERROR'], data: {fake: 'data'}}

  mswServer.use(
    http.post(config.basepath, () => {
      return HttpResponse.json(payload)
    }),
  )

  await expect(getProjects({searchFilter: {}, team: {}})).rejects.toEqual(
    payload.errors,
  )
})
