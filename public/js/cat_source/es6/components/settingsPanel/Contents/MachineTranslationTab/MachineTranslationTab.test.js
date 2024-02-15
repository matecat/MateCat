import React from 'react'
import {act, render, screen} from '@testing-library/react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {MachineTranslationTab} from './MachineTranslationTab'
import {mswServer} from '../../../../../../../mocks/mswServer'
import {http, HttpResponse} from 'msw'
import projectTemplatesMock from '../../../../../../../mocks/projectTemplateMock'
import {
  mmtKeysMock,
  mtEnginesMock,
} from '../../../../../../../mocks/mtEnginesMock'
import userEvent from '@testing-library/user-event'

beforeEach(() => {
  global.config = {
    basepath: 'http://localhost/',
    enableMultiDomainApi: false,
    ajaxDomainsNumber: 20,
    isLoggedIn: 1,
  }

  mswServer.use(
    http.get(`${config.basepath}api/v3/mmt/:engineId/keys`, () => {
      return HttpResponse.json(mmtKeysMock)
    }),
  )
})

test('Render Machine translation tab - not logged', async () => {
  global.config.isLoggedIn = false
  const values = {
    mtEngines: mtEnginesMock,
    setMtEngines: () => {},
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: () => {},
    currentProjectTemplate: projectTemplatesMock.items[0],
    projectTemplates: projectTemplatesMock.items,
  }
  render(
    <SettingsPanelContext.Provider value={values}>
      <MachineTranslationTab />
    </SettingsPanelContext.Provider>,
  )
  const loginButton = screen.getByTestId('login-button')
  expect(loginButton).toBeInTheDocument()
  await userEvent.click(loginButton)
  expect(values.openLoginModal.mock.calls).toHaveLength(1)

  expect(
    screen.queryByPlaceholderText('Choose provider'),
  ).not.toBeInTheDocument()

  const mtName = screen.getByText('MyMemory')
  expect(mtName).toBeInTheDocument()

  const checkboxMtActive = screen.getByTestId('checkbox-mt-active')
  expect(checkboxMtActive).toBeChecked()
})
