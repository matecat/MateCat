import React from 'react'
import {render, renderHook} from '@testing-library/react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {ANALYSIS_SCHEMA_KEYS, AnalysisTab} from './AnalysisTab'
import projectTemplatesMock from '../../../../../../../mocks/projectTemplateMock'
import {mswServer} from '../../../../../../../mocks/mswServer'
import {http, HttpResponse} from 'msw'
import payableRateTemplateMock from '../../../../../../../mocks/payableRateTemplateMock'
import useTemplates from '../../../../hooks/useTemplates'

beforeEach(() => {
  global.config = {
    basepath: 'http://localhost/',
    enableMultiDomainApi: false,
    ajaxDomainsNumber: 20,
    isLoggedIn: 1,
    is_cattool: false,
  }

  mswServer.use(
    http.get(`${config.basepath}api/v2/payable_rate`, () => {
      return HttpResponse.json(payableRateTemplateMock)
    }),
  )
})

test('Render Analysis Tab', () => {
  const {result} = renderHook(() => useTemplates(ANALYSIS_SCHEMA_KEYS))
  const values = {
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: () => {},
    currentProjectTemplate: projectTemplatesMock.items[0],
    projectTemplates: projectTemplatesMock.items,
    analysisTemplates: result.current,
  }
  render(
    <SettingsPanelContext.Provider value={values}>
      <AnalysisTab />
    </SettingsPanelContext.Provider>,
  )
})
