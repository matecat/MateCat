import {render, screen, waitFor} from '@testing-library/react'
import {http, HttpResponse} from 'msw'
import React from 'react'

import {mswServer} from '../../mocks/mswServer'
import {ApplicationWrapperContext} from '../components/common/ApplicationWrapper/ApplicationWrapperContext'
import {ANALYSIS_STATUS} from '../constants/Constants'

jest.mock('../sse/SocketListener', () => () => null)
jest.mock('./mountPage', () => ({mountPage: jest.fn()}))
jest.mock('../components/analyze/AnalyzeMain', () => () => (
  <div data-testid="analyze-main" />
))
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
  job_password: 'jobpass',
  project_access_token: 'tok123',
}

import AnalyzePage from './AnalyzePage'

const renderPage = () =>
  render(
    <ApplicationWrapperContext.Provider
      value={{isUserLogged: true, userInfo: {user: {uid: 1}}}}
    >
      <AnalyzePage />
    </ApplicationWrapperContext.Provider>,
  )

describe('AnalyzePage', () => {
  afterEach(() => {
    mswServer.resetHandlers()
  })

  test('jobAnalysis flow: filters chunks by password and polls until DONE', async () => {
    global.config.jobAnalysis = true

    let call = 0
    mswServer.use(
      http.post('*/api/app/get-volume-analysis', () => {
        call += 1
        return HttpResponse.json({
          summary: {
            status: call <= 2 ? ANALYSIS_STATUS.BUSY : ANALYSIS_STATUS.DONE,
            total_segments: 60000,
          },
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
      http.get('*/api/v2/projects/:id/:password', () =>
        HttpResponse.json({project: {id: 20, name: 'Analyzed project'}}),
      ),
    )

    renderPage()

    await waitFor(() =>
      expect(screen.getByTestId('analyze-main')).toBeInTheDocument(),
    )
    await waitFor(() => expect(call).toBeGreaterThan(2), {timeout: 15000})
  }, 20000)

  test('non-jobAnalysis flow uses getVolumeAnalysis + getProject directly', async () => {
    global.config.jobAnalysis = false

    mswServer.use(
      http.post('*/api/app/get-volume-analysis', () =>
        HttpResponse.json({
          summary: {status: ANALYSIS_STATUS.DONE, total_segments: 5},
        }),
      ),
      http.get('*/api/v2/projects/:id/:password', () =>
        HttpResponse.json({project: {id: 20, name: 'Direct project'}}),
      ),
    )

    renderPage()

    await waitFor(() =>
      expect(screen.getByTestId('analyze-main')).toBeInTheDocument(),
    )
    expect(screen.getByTestId('cookie-consent')).toBeInTheDocument()
  })
})
