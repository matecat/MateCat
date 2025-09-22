import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {getProjectByToken} from './index'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
}

const fakeData = {
  successfull: {
    project: {
      id: 19,
      project_access_token: 'abcdefghijklmnxxx',
      name: '04Ago',
    },
  },
  wrong: {errors: [{code: 0, message: 'No project found.'}], data: []},
}

test('Works fine with correct project id and token', async () => {
  mswServer.use(
    ...[
      http.get(config.basepath + 'api/app/projects/:id/token/:project_access_token', () => {
        return HttpResponse.json(fakeData.successfull)
      }),
    ],
  )
  const response = await getProjectByToken(19, 'abcdefghijklmnxxx')
  expect(response).toEqual(fakeData.successfull)
})

test('Error with wrong project id or token', async () => {
  mswServer.use(
    ...[
      http.get(config.basepath + 'api/app/projects/:id/token/:project_access_token', () => {
        return HttpResponse.json(fakeData.wrong)
      }),
    ],
  )

  const result = await getProjectByToken(19, 'abcdefghijklmnxxx').catch((error) => error)

  expect(result.errors).toEqual(fakeData.wrong.errors)
})
