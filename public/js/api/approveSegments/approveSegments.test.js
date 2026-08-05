import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {approveSegments} from './approveSegments'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  id_job: '77',
  id_client: 'client-1',
  // In a revise view the server hands the page the review password of the phase
  // being worked in, while `password` stays the job password.
  password: 'jobpwd',
  currentPassword: 'r1pwd',
  revisionNumber: 1,
}

test('addresses the route with the review password, not the job password', async () => {
  // The backend resolves which revision phase the caller may act in from this
  // password. Sending the job password would resolve every request to the
  // translate phase and the declared revision_number would be refused.
  let form
  mswServer.use(
    http.post(
      config.basepath + 'api/v2/jobs/77/r1pwd/segments/status',
      async ({request}) => {
        form = await request.formData()
        return HttpResponse.json({data: 'OK'})
      },
    ),
  )

  const response = await approveSegments(['1', '2'])

  expect(response).toEqual({data: 'OK'})
  expect(form.get('revision_number')).toBe('1')
  expect(form.get('status')).toBe('APPROVED')
  expect(form.get('client_id')).toBe('client-1')
})

test('sends the second pass status and revision number in R2', async () => {
  let form
  mswServer.use(
    http.post(
      config.basepath + 'api/v2/jobs/77/r2pwd/segments/status',
      async ({request}) => {
        form = await request.formData()
        return HttpResponse.json({data: 'OK'})
      },
    ),
  )

  await approveSegments(['1'], '77', 'r2pwd', 2)

  expect(form.get('revision_number')).toBe('2')
  expect(form.get('status')).toBe('APPROVED2')
})

test('rejects with the errors payload on a non-ok status', async () => {
  const errors = [{code: -1, message: 'Invalid revision number'}]
  mswServer.use(
    http.post(
      config.basepath + 'api/v2/jobs/77/r1pwd/segments/status',
      () => HttpResponse.json({errors}, {status: 400}),
    ),
  )

  const result = await approveSegments(['1']).catch((error) => error)

  expect(result.errors).toEqual(errors)
})
