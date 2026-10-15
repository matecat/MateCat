import {render, screen, waitFor} from '@testing-library/react'
import {http, HttpResponse} from 'msw'
import React from 'react'

import {mswServer} from '../../mocks/mswServer'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import {ANALYSIS_STATUS} from '../constants/Constants'

jest.mock('../sse/SocketListener', () => () => null)
jest.mock('./mountPage', () => ({mountPage: jest.fn()}))
const mockAnalyzeMainProps = []
jest.mock('../components/analyze/AnalyzeMain', () => (props) => {
  mockAnalyzeMainProps.push(props)
  return <div data-testid="analyze-main" />
})
jest.mock('../components/common/CookieConsent', () => ({
  CookieConsent: () => <div data-testid="cookie-consent" />,
}))
jest.mock('../components/header/Header', () => () => (
  <div data-testid="header" />
))

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  id_project: 20,
  id_job: 1,
  job_password: 'jobpass',
  project_access_token: 'tok123',
}

import AnalyzePage from './AnalyzePage'

// AnalyzeMain still receives Immutable props; read the last rendered ones
const lastAnalysis = () => {
  const {volumeAnalysis} = mockAnalyzeMainProps.at(-1)
  return volumeAnalysis ? volumeAnalysis.toJS() : undefined
}

const renderPage = () =>
  render(
    <ApplicationWrapperContext.Provider
      value={{isUserLogged: true, userInfo: {user: {uid: 1}}}}
    >
      <AnalyzePage />
    </ApplicationWrapperContext.Provider>,
  )

describe('AnalyzePage', () => {
  beforeEach(() => {
    global.config.jobAnalysis = false
    mockAnalyzeMainProps.length = 0
  })

  afterEach(() => {
    mswServer.resetHandlers()
  })

  test('jobAnalysis flow passes only the requested job and chunk', async () => {
    global.config.jobAnalysis = true

    let call = 0
    mswServer.use(
      http.post('*/api/app/get-volume-analysis', () => {
        call += 1
        return HttpResponse.json({
          summary: {status: ANALYSIS_STATUS.DONE, total_segments: 60000},
          jobs: [
            {
              id: 1,
              chunks: [
                {password: 'jobpass', id: 1},
                {password: 'other', id: 2},
              ],
            },
          ],
        })
      }),
      http.get('*/api/app/projects/:id/token/:token', () =>
        HttpResponse.json({project: {id: 20, name: 'Analyzed project'}}),
      ),
    )

    renderPage()

    expect(await screen.findByTestId('analyze-main')).toBeInTheDocument()
    await waitFor(() => expect(lastAnalysis()).toBeDefined())
    const {jobs} = lastAnalysis()
    expect(jobs).toHaveLength(1)
    expect(jobs[0].id).toBe(1)
    expect(jobs[0].chunks).toHaveLength(1)
    expect(jobs[0].chunks[0].password).toBe('jobpass')
    expect(mockAnalyzeMainProps.at(-1).project.get('name')).toBe(
      'Analyzed project',
    )
    expect(call).toBe(1)
  })

  test('non-jobAnalysis flow uses getVolumeAnalysis + getProject directly', async () => {
    const jobs = [
      {id: 1, chunks: [{password: 'a'}, {password: 'b'}]},
      {id: 2, chunks: [{password: 'c'}]},
    ]
    mswServer.use(
      http.post('*/api/app/get-volume-analysis', () =>
        HttpResponse.json({
          summary: {status: ANALYSIS_STATUS.DONE, total_segments: 5},
          jobs,
        }),
      ),
      http.get('*/api/v2/projects/:id/:password', () =>
        HttpResponse.json({project: {id: 20, name: 'Direct project'}}),
      ),
    )

    renderPage()

    expect(await screen.findByTestId('analyze-main')).toBeInTheDocument()
    expect(screen.getByTestId('cookie-consent')).toBeInTheDocument()
    await waitFor(() => expect(lastAnalysis()).toBeDefined())
    expect(lastAnalysis().jobs).toEqual(jobs)
    expect(mockAnalyzeMainProps.at(-1).project.get('name')).toBe(
      'Direct project',
    )
  })
})
