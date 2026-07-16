import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {getTeamUsers} from './index'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  id_job: 12345,
  password: 'df7d197122d8',
  // A team name is present in config but MUST NOT be used to address the endpoint:
  // the roster is fetched via the (unguessable) job password capability instead.
  id_team: 42,
  team_name: 'Acme Localization Team',
}

const fakeMembers = [
  {uid: 1, first_name: 'Jane', last_name: 'Doe'},
  {uid: 2, first_name: 'John', last_name: 'Roe'},
]

test('fetches members via the job id + password capability route (no team name)', async () => {
  let requestedUrl
  let params
  mswServer.use(
    http.get(
      config.basepath + 'api/app/jobs/:idJob/:password/team-members',
      ({request, params: p}) => {
        requestedUrl = request.url
        params = p
        return HttpResponse.json(fakeMembers)
      },
    ),
  )

  const response = await getTeamUsers()

  expect(response).toEqual(fakeMembers)
  // Capability comes from config job credentials, not a guessable team name.
  expect(params.idJob).toBe(String(config.id_job))
  expect(params.password).toBe(config.password)
  // The guessable team-name surface is gone.
  expect(requestedUrl).not.toContain('/teams/')
  expect(requestedUrl).not.toContain('members/public')
  expect(requestedUrl).not.toContain(encodeURIComponent(config.team_name))
})

test('allows explicit job id + password override', async () => {
  let params
  mswServer.use(
    http.get(
      config.basepath + 'api/app/jobs/:idJob/:password/team-members',
      ({params: p}) => {
        params = p
        return HttpResponse.json(fakeMembers)
      },
    ),
  )

  await getTeamUsers({idJob: 999, password: 'overridepass'})

  expect(params.idJob).toBe('999')
  expect(params.password).toBe('overridepass')
})

test('rejects with the errors payload on a failure response', async () => {
  const errors = [{code: 0, message: 'Not found.'}]
  mswServer.use(
    http.get(
      config.basepath + 'api/app/jobs/:idJob/:password/team-members',
      () => HttpResponse.json({errors}),
    ),
  )

  const result = await getTeamUsers().catch((e) => e)

  expect(result).toEqual(errors)
})
