import {rest} from 'msw'
import {mswServer} from '../../../../../mocks/mswServer'
import {getProject} from '.'

global.config = {
  basepath: '/',
}

const fakeData = {
  successfull: {
    project: {
      id: 19,
      password: 'df7d197122d8',
      name: '04Ago',
    },
  },
  wrong: {errors: [{code: 0, message: 'No project found.'}], data: []},
}

test('Works fine with correct project id and password', async () => {
  mswServer.use(
    ...[
      rest.get('/api/v2/projects/:id/:password', (req, res, ctx) => {
        return res(ctx.status(200), ctx.json(fakeData.successfull))
      }),
    ],
  )

  const result = await getProject(19, 'df7d197122d8')
  expect(result).toEqual(fakeData.successfull)
})

test('Error with wrong project id or password', async () => {
  mswServer.use(
    ...[
      rest.get('/api/v2/projects/:id/:password', (req, res, ctx) => {
        return res(ctx.status(200), ctx.json(fakeData.wrong))
      }),
    ],
  )

  const result = await getProject(19, 'df7d197a122d8').catch(
    ({response, errors}) => errors,
  )
  expect(result).toEqual(fakeData.wrong.errors)
})
