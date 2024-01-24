import React from 'react'
import {render, renderHook, waitFor, screen, act} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import useProjectTemplates from '../../hooks/useProjectTemplates'
import {ProjectTemplate} from './ProjectTemplate'
import {SettingsPanelContext} from './SettingsPanelContext'
import {mswServer} from '../../../../../mocks/mswServer'
import {HttpResponse, http} from 'msw'
import projectTemplatesMock from '../../../../../mocks/projectTemplateMock'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  ajaxDomainsNumber: 20,
}

beforeEach(() => {
  mswServer.use(
    http.get(`${config.basepath}api/v3/project-template/`, () => {
      return HttpResponse.json(projectTemplatesMock)
    }),
  )
})

test('Render properly', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useProjectTemplates(true))

  await waitFor(() => {
    expect(result.current.projectTemplates?.length).toBe(2)
  })

  let {projectTemplates, setProjectTemplates} = result.current

  render(
    <SettingsPanelContext.Provider
      value={{projectTemplates, setProjectTemplates}}
    >
      <ProjectTemplate />
    </SettingsPanelContext.Provider>,
  )

  const selectLabel = screen.getByText('default template')
  expect(selectLabel).toBeInTheDocument()

  await act(async () => user.click(selectLabel))

  const itemTestingTemplate = screen.getByText('Testing template')
  expect(itemTestingTemplate).toBeInTheDocument()

  await act(async () => user.click(itemTestingTemplate))

  projectTemplates = result.current.projectTemplates
  render(
    <SettingsPanelContext.Provider
      value={{projectTemplates, setProjectTemplates}}
    >
      <ProjectTemplate />
    </SettingsPanelContext.Provider>,
  )

  expect(projectTemplates.find(({isSelected}) => isSelected).id).toBe(3)
  expect(result.current.currentProjectTemplate.id).toBe(3)
})
