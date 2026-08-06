import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../mocks/mswServer'
import {updateJobMetadata} from './updateJobMetadata'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  id_job: '42',
  password: 'testpwd',
}

const url = `${config.basepath}api/app/jobs/${config.id_job}/${config.password}/metadata`

describe('updateJobMetadata', () => {
  describe('request URL', () => {
    test('uses idJob and password from config by default', async () => {
      let requestUrl
      mswServer.use(
        http.post('*', ({request}) => {
          requestUrl = request.url
          return HttpResponse.json({})
        }),
      )

      await updateJobMetadata({})

      expect(requestUrl).toBe(url)
    })

    test('prefers explicit idJob and password over config', async () => {
      let requestUrl
      mswServer.use(
        http.post('*', ({request}) => {
          requestUrl = request.url
          return HttpResponse.json({})
        }),
      )

      await updateJobMetadata({idJob: '99', password: 'other'})

      expect(requestUrl).toBe(
        `${config.basepath}api/app/jobs/99/other/metadata`,
      )
    })
  })

  describe('request body serialization', () => {
    const captureBody = () => {
      let received
      mswServer.use(
        http.post(url, async ({request}) => {
          received = await request.json()
          return HttpResponse.json({})
        }),
      )
      return () => received
    }

    test('sends body as an array of {key, value} pairs', async () => {
      const getBody = captureBody()
      await updateJobMetadata({characterCounterMode: 'chars'})
      expect(Array.isArray(getBody())).toBe(true)
      expect(getBody()[0]).toMatchObject({key: expect.any(String), value: expect.anything()})
    })

    test('includes tmPrioritization when boolean true', async () => {
      const getBody = captureBody()
      await updateJobMetadata({tmPrioritization: true})
      expect(getBody()).toContainEqual({key: 'tm_prioritization', value: true})
    })

    test('includes tmPrioritization when boolean false', async () => {
      const getBody = captureBody()
      await updateJobMetadata({tmPrioritization: false})
      expect(getBody()).toContainEqual({key: 'tm_prioritization', value: false})
    })

    test('excludes tmPrioritization when undefined', async () => {
      const getBody = captureBody()
      await updateJobMetadata({})
      expect(getBody().map(({key}) => key)).not.toContain('tm_prioritization')
    })

    test('excludes tmPrioritization when a non-boolean value is passed', async () => {
      const getBody = captureBody()
      await updateJobMetadata({tmPrioritization: 'yes'})
      expect(getBody().map(({key}) => key)).not.toContain('tm_prioritization')
    })

    test('includes characterCounterCountTags when boolean true', async () => {
      const getBody = captureBody()
      await updateJobMetadata({characterCounterCountTags: true})
      expect(getBody()).toContainEqual({
        key: 'character_counter_count_tags',
        value: true,
      })
    })

    test('includes characterCounterCountTags when boolean false', async () => {
      const getBody = captureBody()
      await updateJobMetadata({characterCounterCountTags: false})
      expect(getBody()).toContainEqual({
        key: 'character_counter_count_tags',
        value: false,
      })
    })

    test('excludes characterCounterCountTags when a non-boolean value is passed', async () => {
      const getBody = captureBody()
      await updateJobMetadata({characterCounterCountTags: 1})
      expect(getBody().map(({key}) => key)).not.toContain(
        'character_counter_count_tags',
      )
    })

    test('includes characterCounterMode', async () => {
      const getBody = captureBody()
      await updateJobMetadata({characterCounterMode: 'words'})
      expect(getBody()).toContainEqual({
        key: 'character_counter_mode',
        value: 'words',
      })
    })

    test('includes subfilteringHandlers', async () => {
      const getBody = captureBody()
      const handlers = {handler: 'test'}
      await updateJobMetadata({subfilteringHandlers: handlers})
      expect(getBody()).toContainEqual({
        key: 'subfiltering_handlers',
        value: handlers,
      })
    })

    test('includes mandatoryIssues', async () => {
      const getBody = captureBody()
      const issues = ['r1', 'r2']
      await updateJobMetadata({mandatoryIssues: issues})
      expect(getBody()).toContainEqual({key: 'mandatory_issues', value: issues})
    })

    test('includes mandatoryIssues when empty array', async () => {
      const getBody = captureBody()
      await updateJobMetadata({mandatoryIssues: []})
      expect(getBody()).toContainEqual({key: 'mandatory_issues', value: []})
    })

    test('omits undefined fields from the payload', async () => {
      const getBody = captureBody()
      await updateJobMetadata({characterCounterMode: 'chars'})
      const keys = getBody().map(({key}) => key)
      expect(keys).not.toContain('tm_prioritization')
      expect(keys).not.toContain('character_counter_count_tags')
      expect(keys).not.toContain('subfiltering_handlers')
      expect(keys).not.toContain('mandatory_issues')
    })

    test('sends an empty array when all fields are undefined', async () => {
      const getBody = captureBody()
      await updateJobMetadata({})
      expect(getBody()).toEqual([])
    })
  })

  describe('request headers and credentials', () => {
    test('sends Content-Type: application/json', async () => {
      let contentType
      mswServer.use(
        http.post(url, ({request}) => {
          contentType = request.headers.get('Content-Type')
          return HttpResponse.json({})
        }),
      )

      await updateJobMetadata({})

      expect(contentType).toBe('application/json')
    })
  })

  describe('response handling', () => {
    test('returns data from a successful response', async () => {
      mswServer.use(
        http.post(url, () =>
          HttpResponse.json({status: 'OK', updated: true}),
        ),
      )

      const result = await updateJobMetadata({})

      expect(result).toEqual({status: 'OK', updated: true})
    })

    test('strips the errors field from the returned data', async () => {
      mswServer.use(
        http.post(url, () => HttpResponse.json({status: 'OK', errors: []})),
      )

      const result = await updateJobMetadata({})

      expect(result).not.toHaveProperty('errors')
    })

    test('rejects with the response on a non-ok HTTP status', async () => {
      mswServer.use(http.post(url, () => new HttpResponse(null, {status: 500})))

      const result = await updateJobMetadata({}).catch((e) => e)

      expect(result.ok).toBe(false)
      expect(result.status).toBe(500)
    })

    test('rejects with the errors array when the payload contains errors', async () => {
      const errors = [{code: 10, message: 'invalid'}]
      mswServer.use(http.post(url, () => HttpResponse.json({errors})))

      const result = await updateJobMetadata({}).catch((e) => e)

      expect(result).toEqual(errors)
    })
  })
})
