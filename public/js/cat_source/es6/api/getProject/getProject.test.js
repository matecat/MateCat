import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../../../mocks/mswServer'
import {getProject} from '.'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
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
      http.get(config.basepath + 'api/v2/projects/:id/:password', () => {
        return HttpResponse.json(fakeData.successfull)
      }),
    ],
  )
  const response = await getProject(19, 'df7d197122d8')
  expect(response).toEqual(fakeData.successfull)
})

test('Error with wrong project id or password', async () => {
  mswServer.use(
    ...[
      http.get(config.basepath + 'api/v2/projects/:id/:password', () => {
        return HttpResponse.json(fakeData.wrong)
      }),
    ],
  )

  const result = await getProject(19, 'df7d197a122d8').catch((error) => error)

  expect(result.errors).toEqual(fakeData.wrong.errors)
})
