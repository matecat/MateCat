import {act, renderHook} from '@testing-library/react'
import {http, HttpResponse} from 'msw'
import {mswServer} from '../../mocks/mswServer'
import {
  buildVolumeAnalysis,
  projectMock,
  volumeAnalysisBuilders,
} from '../../mocks/volumeAnalysisMock'
import {ANALYSIS_STATUS} from '../constants/Constants'
import useVolumeAnalysis, {getPollingInterval} from './useVolumeAnalysis'

// jsdom has no global setImmediate; take Node's, which fake timers leave alone
const {setImmediate: realSetImmediate} = jest.requireActual('timers')

const buildJob = (id, passwords) =>
  volumeAnalysisBuilders.buildJob({
    id,
    source: 'en-US',
    sourceName: 'English (USA)',
    target: 'it-IT',
    targetName: 'Italian',
    chunks: passwords.map((password) =>
      volumeAnalysisBuilders.buildChunk({idJob: id, password, files: []}),
    ),
  })

const FAKE_TIMERS = {doNotFake: ['nextTick', 'queueMicrotask']}

const ANALYSIS_URL = '*/api/app/get-volume-analysis'
const PROJECT_URL = '*/api/v2/projects/:id/:password'
const PROJECT_BY_TOKEN_URL = '*/api/app/projects/:id/token/:token'

const PROJECT_OPTIONS = {
  jobAnalysis: false,
  idProject: 20,
  password: 'projpass',
}

const JOB_OPTIONS = {
  jobAnalysis: true,
  idProject: 20,
  password: 'projpass',
  idJob: 101,
  jobPassword: 'chunkpass2',
  projectAccessToken: 'tok123',
}

// Lets pending network work settle without advancing the fake timers
const flushNetwork = async () => {
  for (let i = 0; i < 20; i++) {
    await act(() => new Promise((resolve) => realSetImmediate(resolve)))
  }
}

const advance = async (ms) => {
  act(() => {
    jest.advanceTimersByTime(ms)
  })
  await flushNetwork()
}

let analysisRequests
let projectRequests
let projectByTokenRequests

// Each analysis request answers the next status; the last one repeats
const mockAnalysis = (statuses, summary = {}, jobs) => {
  mswServer.use(
    http.post(ANALYSIS_URL, async ({request}) => {
      const body = Object.fromEntries(await request.formData())
      analysisRequests.push(body)
      const status =
        statuses[Math.min(analysisRequests.length, statuses.length) - 1]
      return HttpResponse.json(
        buildVolumeAnalysis({summary: {status, ...summary}, jobs}),
      )
    }),
  )
}

const mockProject = () => {
  mswServer.use(
    http.get(PROJECT_URL, ({params}) => {
      projectRequests.push(params)
      return HttpResponse.json({
        project: {...projectMock, name: `Project ${projectRequests.length}`},
      })
    }),
    http.get(PROJECT_BY_TOKEN_URL, ({params}) => {
      projectByTokenRequests.push(params)
      return HttpResponse.json({project: projectMock})
    }),
  )
}

const renderVolumeAnalysis = (options) =>
  renderHook(() => useVolumeAnalysis(options))

beforeAll(() => {
  global.config = {basepath: 'http://localhost/', enableMultiDomainApi: false}
})

beforeEach(() => {
  analysisRequests = []
  projectRequests = []
  projectByTokenRequests = []
  jest.useFakeTimers(FAKE_TIMERS)
  mockProject()
})

afterEach(() => {
  jest.useRealTimers()
  mswServer.resetHandlers()
})

describe('getPollingInterval', () => {
  test('polls every second up to 50000 segments', () => {
    expect(getPollingInterval(10)).toBe(1000)
    expect(getPollingInterval(50000)).toBe(1000)
  })

  test('slows down to total_segments / 20 above 50000 segments', () => {
    expect(getPollingInterval(60000)).toBe(3000)
  })
})

describe('useVolumeAnalysis in project mode', () => {
  test('loads analysis and project, polls until DONE, then refreshes the project once', async () => {
    mockAnalysis([
      ANALYSIS_STATUS.BUSY,
      ANALYSIS_STATUS.FAST_OK,
      ANALYSIS_STATUS.DONE,
    ])
    const {result} = renderVolumeAnalysis(PROJECT_OPTIONS)

    expect(result.current.loading).toBe(true)
    expect(result.current.analysis).toBeUndefined()

    await flushNetwork()
    expect(result.current.loading).toBe(false)
    expect(result.current.error).toBeNull()
    expect(result.current.analysis.summary.status).toBe(ANALYSIS_STATUS.BUSY)
    expect(result.current.project.name).toBe('Project 1')
    expect(analysisRequests).toEqual([{id_project: '20', password: 'projpass'}])
    expect(projectRequests).toMatchObject([{id: '20', password: 'projpass'}])

    await advance(999)
    expect(analysisRequests).toHaveLength(1)

    await advance(1)
    expect(analysisRequests).toHaveLength(2)
    expect(result.current.analysis.summary.status).toBe(ANALYSIS_STATUS.FAST_OK)
    expect(projectRequests).toHaveLength(1)

    await advance(1000)
    expect(analysisRequests).toHaveLength(3)
    expect(result.current.analysis.summary.status).toBe(ANALYSIS_STATUS.DONE)
    expect(projectRequests).toHaveLength(2)
    expect(result.current.project.name).toBe('Project 2')

    await advance(10000)
    expect(analysisRequests).toHaveLength(3)
    expect(projectRequests).toHaveLength(2)
    expect(jest.getTimerCount()).toBe(0)
  })

  test('does not poll when the analysis is already DONE', async () => {
    mockAnalysis([ANALYSIS_STATUS.DONE])
    const {result} = renderVolumeAnalysis(PROJECT_OPTIONS)

    await flushNetwork()
    await advance(10000)
    expect(result.current.analysis.summary.status).toBe(ANALYSIS_STATUS.DONE)
    expect(analysisRequests).toHaveLength(1)
    expect(projectRequests).toHaveLength(1)
  })

  test('stops on NOT_TO_ANALYZE and refreshes the project once', async () => {
    mockAnalysis([ANALYSIS_STATUS.NEW, ANALYSIS_STATUS.NOT_TO_ANALYZE])
    const {result} = renderVolumeAnalysis(PROJECT_OPTIONS)

    await flushNetwork()
    await advance(1000)
    expect(result.current.analysis.summary.status).toBe(
      ANALYSIS_STATUS.NOT_TO_ANALYZE,
    )
    expect(projectRequests).toHaveLength(2)

    await advance(10000)
    expect(analysisRequests).toHaveLength(2)
    expect(projectRequests).toHaveLength(2)
  })

  test('stops on EMPTY without refreshing the project', async () => {
    mockAnalysis([ANALYSIS_STATUS.BUSY, ANALYSIS_STATUS.EMPTY])
    const {result} = renderVolumeAnalysis(PROJECT_OPTIONS)

    await flushNetwork()
    await advance(1000)
    expect(result.current.analysis.summary.status).toBe(ANALYSIS_STATUS.EMPTY)

    await advance(10000)
    expect(analysisRequests).toHaveLength(2)
    expect(projectRequests).toHaveLength(1)
    expect(jest.getTimerCount()).toBe(0)
  })

  test('never polls when the first response is EMPTY', async () => {
    mockAnalysis([ANALYSIS_STATUS.EMPTY])
    renderVolumeAnalysis(PROJECT_OPTIONS)

    await flushNetwork()
    await advance(10000)
    expect(analysisRequests).toHaveLength(1)
  })

  test('polls every total_segments / 20 ms above 50000 segments', async () => {
    mockAnalysis([ANALYSIS_STATUS.BUSY], {total_segments: 60000})
    renderVolumeAnalysis(PROJECT_OPTIONS)

    await flushNetwork()
    await advance(2999)
    expect(analysisRequests).toHaveLength(1)

    await advance(1)
    expect(analysisRequests).toHaveLength(2)

    await advance(3000)
    expect(analysisRequests).toHaveLength(3)
  })
})

describe('useVolumeAnalysis in job mode', () => {
  test('keeps only the job and chunk of jobPassword and does not poll', async () => {
    mockAnalysis([ANALYSIS_STATUS.BUSY])
    const {result} = renderVolumeAnalysis(JOB_OPTIONS)

    await flushNetwork()
    expect(result.current.loading).toBe(false)
    expect(analysisRequests).toEqual([
      {id_project: '20', id_job: '101', password: 'chunkpass2'},
    ])
    expect(projectByTokenRequests).toMatchObject([{id: '20', token: 'tok123'}])
    expect(projectRequests).toHaveLength(0)
    expect(result.current.project).toEqual(projectMock)

    const {jobs} = result.current.analysis
    expect(jobs).toHaveLength(1)
    expect(jobs[0].id).toBe(101)
    expect(jobs[0].chunks.map((chunk) => chunk.password)).toEqual([
      'chunkpass2',
    ])
    expect(jobs[0].chunkPosition).toBe(2)
    expect(jobs[0].chunkCount).toBe(2)
    expect(result.current.analysis.summary.status).toBe(ANALYSIS_STATUS.BUSY)

    await advance(10000)
    expect(analysisRequests).toHaveLength(1)
    expect(jest.getTimerCount()).toBe(0)
  })

  test('keeps chunk 2 of 3 with its original position and count', async () => {
    const jobs = [
      buildJob(101, ['first', 'second', 'third']),
      buildJob(102, ['other']),
    ]
    const response = buildVolumeAnalysis({jobs})
    const snapshot = JSON.parse(JSON.stringify(response))
    mswServer.use(http.post(ANALYSIS_URL, () => HttpResponse.json(response)))
    const {result} = renderVolumeAnalysis({
      ...JOB_OPTIONS,
      jobPassword: 'second',
    })

    await flushNetwork()
    const [job] = result.current.analysis.jobs
    expect(result.current.analysis.jobs).toHaveLength(1)
    expect(job.id).toBe(101)
    expect(job.chunks.map((chunk) => chunk.password)).toEqual(['second'])
    expect(job.chunkPosition).toBe(2)
    expect(job.chunkCount).toBe(3)
    expect(response).toEqual(snapshot)
  })

  test('does not pick a job whose id differs from idJob', async () => {
    // Job 101 comes first and holds the same chunk password as job 102
    mockAnalysis([ANALYSIS_STATUS.DONE], {}, [
      buildJob(101, ['shared', 'x']),
      buildJob(102, ['y', 'shared']),
    ])
    const {result} = renderVolumeAnalysis({
      ...JOB_OPTIONS,
      idJob: '102',
      jobPassword: 'shared',
    })

    await flushNetwork()
    const [job] = result.current.analysis.jobs
    expect(job.id).toBe(102)
    expect(job.chunkPosition).toBe(2)
    expect(job.chunkCount).toBe(2)
  })

  test('returns no jobs when only another job holds jobPassword', async () => {
    mockAnalysis([ANALYSIS_STATUS.DONE])
    const {result} = renderVolumeAnalysis({
      ...JOB_OPTIONS,
      idJob: 999,
    })

    await flushNetwork()
    expect(result.current.analysis.jobs).toEqual([])
  })

  test('returns no jobs when no chunk matches jobPassword', async () => {
    mockAnalysis([ANALYSIS_STATUS.DONE])
    const {result} = renderVolumeAnalysis({
      ...JOB_OPTIONS,
      jobPassword: 'unknown',
    })

    await flushNetwork()
    expect(result.current.analysis.jobs).toEqual([])
    expect(result.current.error).toBeNull()
  })
})

describe('useVolumeAnalysis lifecycle', () => {
  test('unmount clears the polling timer', async () => {
    mockAnalysis([ANALYSIS_STATUS.BUSY])
    const {unmount} = renderVolumeAnalysis(PROJECT_OPTIONS)

    await flushNetwork()
    expect(jest.getTimerCount()).toBe(1)

    unmount()
    expect(jest.getTimerCount()).toBe(0)
    await advance(10000)
    expect(analysisRequests).toHaveLength(1)
  })

  test('ignores a response that arrives after unmount', async () => {
    let release
    const pending = new Promise((resolve) => {
      release = resolve
    })
    mswServer.use(
      http.post(ANALYSIS_URL, async () => {
        analysisRequests.push({})
        await pending
        return HttpResponse.json(
          buildVolumeAnalysis({summary: {status: ANALYSIS_STATUS.BUSY}}),
        )
      }),
    )
    const consoleError = jest.spyOn(console, 'error')
    const {result, unmount} = renderVolumeAnalysis(PROJECT_OPTIONS)

    await flushNetwork()
    expect(analysisRequests).toHaveLength(1)
    unmount()

    release()
    await flushNetwork()
    expect(result.current.analysis).toBeUndefined()
    expect(result.current.project).toBeUndefined()
    expect(result.current.loading).toBe(true)
    expect(jest.getTimerCount()).toBe(0)
    expect(consoleError).not.toHaveBeenCalled()

    await advance(10000)
    expect(analysisRequests).toHaveLength(1)
    consoleError.mockRestore()
  })

  test('surfaces a failed first load in error', async () => {
    mswServer.use(
      http.post(ANALYSIS_URL, () =>
        HttpResponse.json(
          {errors: [{code: -1, message: 'Wrong password'}]},
          {status: 200},
        ),
      ),
    )
    const {result} = renderVolumeAnalysis(PROJECT_OPTIONS)

    await flushNetwork()
    expect(result.current.loading).toBe(false)
    expect(result.current.error).toEqual([
      {code: -1, message: 'Wrong password'},
    ])
    expect(result.current.analysis).toBeUndefined()
  })

  test('a failed project refresh after DONE keeps the analysis and the last project', async () => {
    mockAnalysis([ANALYSIS_STATUS.BUSY, ANALYSIS_STATUS.DONE])
    let projectCall = 0
    mswServer.use(
      http.get(PROJECT_URL, () => {
        projectCall += 1
        if (projectCall > 1) return new HttpResponse(null, {status: 500})
        return HttpResponse.json({project: projectMock})
      }),
    )
    const {result} = renderVolumeAnalysis(PROJECT_OPTIONS)

    await flushNetwork()
    await advance(1000)
    expect(projectCall).toBe(2)
    expect(result.current.error).toBeNull()
    expect(result.current.loading).toBe(false)
    expect(result.current.analysis.summary.status).toBe(ANALYSIS_STATUS.DONE)
    expect(result.current.project).toEqual(projectMock)
  })

  test('surfaces a failed getProjectByToken in job mode', async () => {
    mockAnalysis([ANALYSIS_STATUS.DONE])
    mswServer.use(
      http.get(
        PROJECT_BY_TOKEN_URL,
        () => new HttpResponse(null, {status: 500}),
      ),
    )
    const {result} = renderVolumeAnalysis(JOB_OPTIONS)

    await flushNetwork()
    expect(result.current.loading).toBe(false)
    // getProjectByToken parses the empty 500 body, so the error is its rejection
    expect(result.current.error).toBeInstanceOf(Error)
    expect(result.current.analysis).toBeUndefined()
  })

  test('changing options restarts the effect and drops the old timer', async () => {
    mockAnalysis([ANALYSIS_STATUS.BUSY])
    const {result, rerender} = renderHook(
      (options) => useVolumeAnalysis(options),
      {initialProps: PROJECT_OPTIONS},
    )

    await flushNetwork()
    expect(analysisRequests).toEqual([{id_project: '20', password: 'projpass'}])
    expect(jest.getTimerCount()).toBe(1)

    rerender({...PROJECT_OPTIONS, idProject: 21, password: 'otherpass'})
    await flushNetwork()
    expect(analysisRequests).toEqual([
      {id_project: '20', password: 'projpass'},
      {id_project: '21', password: 'otherpass'},
    ])
    expect(result.current.loading).toBe(false)
    expect(jest.getTimerCount()).toBe(1)

    await advance(1000)
    expect(analysisRequests.slice(2)).toEqual([
      {id_project: '21', password: 'otherpass'},
    ])
    expect(
      analysisRequests.filter((request) => request.id_project === '20'),
    ).toHaveLength(1)
  })

  test('surfaces a failed poll in error and stops polling', async () => {
    let call = 0
    mswServer.use(
      http.post(ANALYSIS_URL, () => {
        call += 1
        if (call > 1) return new HttpResponse(null, {status: 500})
        return HttpResponse.json(
          buildVolumeAnalysis({summary: {status: ANALYSIS_STATUS.BUSY}}),
        )
      }),
    )
    const {result} = renderVolumeAnalysis(PROJECT_OPTIONS)

    await flushNetwork()
    expect(result.current.error).toBeNull()

    await advance(1000)
    expect(result.current.error).toBeInstanceOf(Response)
    expect(result.current.error.status).toBe(500)
    expect(result.current.analysis.summary.status).toBe(ANALYSIS_STATUS.BUSY)

    await advance(10000)
    expect(call).toBe(2)
  })
})
