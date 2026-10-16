import {act, render, screen, waitFor} from '@testing-library/react'
import {http, HttpResponse} from 'msw'
import React from 'react'

import {mswServer} from '../../mocks/mswServer'
import {buildVolumeAnalysis, projectMock} from '../../mocks/volumeAnalysisMock'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'

jest.mock('../sse/SocketListener', () => () => null)
jest.mock('./mountPage', () => ({mountPage: jest.fn()}))
jest.mock('../components/common/CookieConsent', () => ({
  CookieConsent: () => <div data-testid="cookie-consent" />,
}))
jest.mock('../components/header/Header', () => () => (
  <div data-testid="header" />
))
// The offer card asks the vendor for a quote: not this page's concern
jest.mock('../components/analyze/OutsourceOfferCard', () => ({
  ...jest.requireActual('../components/analyze/OutsourceOfferCard'),
  OutsourceOfferCard: () => null,
}))

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  id_project: 20,
  password: 'projpass',
  id_job: 102,
  job_password: 'frpass',
  project_access_token: 'tok123',
  support_mail: 'support@matecat.com',
}

import AnalyzePage, {REFRESH_FAILED_NOTICE} from './AnalyzePage'

// jsdom has no global setImmediate; take Node's, which fake timers leave alone
const {setImmediate: realSetImmediate} = jest.requireActual('timers')
const flushNetwork = async () => {
  for (let i = 0; i < 20; i++) {
    await act(() => new Promise((resolve) => realSetImmediate(resolve)))
  }
}

const renderPage = () =>
  render(
    <ApplicationWrapperContext.Provider
      value={{isUserLogged: true, userInfo: {user: {uid: 1}}}}
    >
      <AnalyzePage />
    </ApplicationWrapperContext.Provider>,
  )

const serveProject = (analysis) =>
  mswServer.use(
    http.post('*/api/app/get-volume-analysis', () =>
      HttpResponse.json(analysis),
    ),
    http.get('*/api/v2/projects/:id/:password', () =>
      HttpResponse.json({project: projectMock}),
    ),
  )

const serveJobPage = (analysis) =>
  mswServer.use(
    http.post('*/api/app/get-volume-analysis', () =>
      HttpResponse.json(analysis),
    ),
    http.get('*/api/app/projects/:id/token/:token', () =>
      HttpResponse.json({project: {id: 20, name: 'Analyzed project'}}),
    ),
  )

// The loader sits under the same heading: wait for it to make room
const h1 = async (name) => {
  await waitFor(
    () => expect(screen.queryByText(/^Loading the /)).not.toBeInTheDocument(),
    {timeout: 3000},
  )
  return screen.getByRole('heading', {level: 1, name})
}

describe('AnalyzePage', () => {
  beforeEach(() => {
    global.config.jobAnalysis = false
  })

  afterEach(() => {
    mswServer.resetHandlers()
  })

  test('shows a loader until the analysis arrives', async () => {
    serveProject(buildVolumeAnalysis())
    renderPage()
    expect(screen.getByText('Loading the project analysis')).toBeInTheDocument()
    expect(await h1('Project analysis')).toBeInTheDocument()
  })

  test('several jobs render the tree page', async () => {
    serveProject(buildVolumeAnalysis())
    renderPage()

    expect(await h1('Project analysis')).toBeInTheDocument()
    expect(document.title).toBe('Project analysis - Matecat')
    expect(screen.getByText('2 language combinations')).toBeInTheDocument()
    expect(
      screen.getByRole('region', {name: 'Language combinations'}),
    ).toBeInTheDocument()
    expect(
      screen.getByText('Whole job · 2 chunks · ID 101'),
    ).toBeInTheDocument()
  })

  test('one job split in chunks is a tree too', async () => {
    const analysis = buildVolumeAnalysis()
    serveProject({...analysis, jobs: [analysis.jobs[0]]})
    renderPage()

    expect(await h1('Project analysis')).toBeInTheDocument()
    expect(
      screen.getByRole('region', {name: 'Language combinations'}),
    ).toBeInTheDocument()
  })

  test('one job in one chunk renders the job without a tree', async () => {
    const analysis = buildVolumeAnalysis()
    serveProject({...analysis, jobs: [analysis.jobs[1]]})
    renderPage()

    expect(await h1('Project analysis')).toBeInTheDocument()
    expect(screen.getByText('Job ID 102 · 120 words')).toBeInTheDocument()
    expect(
      screen.queryByRole('region', {name: 'Language combinations'}),
    ).not.toBeInTheDocument()
  })

  test('no text to translate renders the empty page', async () => {
    serveProject(buildVolumeAnalysis({summary: {status: 'EMPTY'}, jobs: []}))
    renderPage()

    expect(await h1('Project analysis')).toBeInTheDocument()
    expect(
      screen.getByRole('alert', {
        name: 'There is no text to translate in these files',
      }),
    ).toBeInTheDocument()
  })

  test('the job page shows the translator’s chunk only', async () => {
    global.config.jobAnalysis = true
    serveJobPage(buildVolumeAnalysis())
    renderPage()

    expect(await h1('Job analysis')).toBeInTheDocument()
    expect(document.title).toBe('Job analysis - Matecat')
    expect(
      screen.getByRole('region', {name: 'en-US › fr-FR'}),
    ).toBeInTheDocument()
    expect(screen.getByText('Job ID 102')).toBeInTheDocument()
    expect(
      screen.getByRole('link', {name: 'Back to translation'}),
    ).toBeInTheDocument()
  })

  test('a job page whose chunk is gone says so', async () => {
    global.config.jobAnalysis = true
    serveJobPage(buildVolumeAnalysis({jobs: []}))
    renderPage()

    expect(await h1('Job analysis')).toBeInTheDocument()
    expect(
      screen.getByRole('alert', {name: 'This job is not in the analysis'}),
    ).toBeInTheDocument()
  })

  test('a failed load says so', async () => {
    mswServer.use(
      http.post('*/api/app/get-volume-analysis', () =>
        HttpResponse.json({errors: [{code: -1, message: 'boom'}]}),
      ),
    )
    renderPage()

    expect(
      await screen.findByRole('alert', {
        name: 'We could not load the analysis',
      }),
    ).toBeInTheDocument()
  })

  test('a project page still counting shows the status, not the empty page', async () => {
    serveProject(
      buildVolumeAnalysis({
        summary: {status: 'NEW', in_queue_before: 0},
        jobs: [],
      }),
    )
    renderPage()

    expect(await h1('Project analysis')).toBeInTheDocument()
    expect(
      screen.getByRole('region', {name: 'Analysis status'}),
    ).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  test('a job page whose project cannot be read says the job is gone', async () => {
    global.config.jobAnalysis = true
    mswServer.use(
      http.post('*/api/app/get-volume-analysis', () =>
        HttpResponse.json(buildVolumeAnalysis()),
      ),
      http.get('*/api/app/projects/:id/token/:token', () =>
        HttpResponse.json({}),
      ),
    )
    renderPage()

    expect(
      await screen.findByRole('alert', {
        name: 'This job is not in the analysis',
      }),
    ).toBeInTheDocument()
    expect(
      screen.queryByText('Loading the job analysis'),
    ).not.toBeInTheDocument()
  })

  test('a refresh that keeps failing leaves the numbers and says so', async () => {
    jest.useFakeTimers({doNotFake: ['nextTick', 'queueMicrotask']})
    let call = 0
    mswServer.use(
      http.post('*/api/app/get-volume-analysis', () => {
        call += 1
        if (call > 1) return new HttpResponse(null, {status: 500})
        return HttpResponse.json(
          buildVolumeAnalysis({
            summary: {status: 'FAST_OK', segments_analyzed: 10},
          }),
        )
      }),
      http.get('*/api/v2/projects/:id/:password', () =>
        HttpResponse.json({project: projectMock}),
      ),
    )
    try {
      renderPage()
      expect(await h1('Project analysis')).toBeInTheDocument()

      // The poll, then its retries after 2, 4 and 8 seconds
      for (const ms of [1000, 2000, 4000, 8000]) {
        await act(async () => {
          jest.advanceTimersByTime(ms)
        })
        await flushNetwork()
      }

      expect(call).toBe(5)
      expect(screen.getByText(REFRESH_FAILED_NOTICE)).toHaveAttribute(
        'role',
        'status',
      )
      expect(
        screen.getByRole('region', {name: 'Language combinations'}),
      ).toBeInTheDocument()
    } finally {
      jest.useRealTimers()
    }
  })
})
