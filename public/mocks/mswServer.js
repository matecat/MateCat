import {setupServer} from 'msw/node'
import {rest} from 'msw'

const handlers = [
  // rest.get('*api/app/user', (req, res, ctx) => {
  //   console.log('app/user')
  //   return res(ctx.status(404))
  // }),
  // rest.post('*', (req, res, ctx) => {
  //   console.log('bla')
  //   return res(
  //     ctx.status(200),
  //     ctx.json({
  //       data: {
  //         jobs: {},
  //         summary: {},
  //       },
  //       errors: [],
  //     }),
  //   )
  // }),
  // rest.get('*api/v2/projects/:id/*', (req, res, ctx) => {
  //   return res(ctx.status(500))
  // }),
]

export const mswServer = setupServer(...handlers)
