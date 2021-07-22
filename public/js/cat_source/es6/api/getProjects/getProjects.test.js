import {rest} from 'msw'

import {getProjects} from '.'
import {mswServer} from '../../../../../mocks/mswServer'

test('works properly with empty filter', async () => {
  const payload = {fake: 'data'}

  mswServer.use(
    rest.post('*.ajax.localhost', (req, res, ctx) => {
      return res(ctx.status(200), ctx.json(payload))
    }),
  )

  global.config = {
    enableMultiDomainApi: true,
    ajaxDomainsNumber: 20,
  }

  const data = await getProjects({searchFilter: {}, team: {}})

  expect(data).toEqual(payload)
})

test('works properly with full filter', async () => {
  const payload = {fake: 'data'}

  mswServer.use(
    rest.post('*.ajax.localhost', (req, res, ctx) => {
      return res(ctx.status(200), ctx.json(payload))
    }),
  )

  global.config = {
    enableMultiDomainApi: true,
    ajaxDomainsNumber: 20,
  }

  const data = await getProjects({
    searchFilter: {filter: {foo: 'bar'}},
    team: {},
  })

  expect(data).toEqual(payload)
})
