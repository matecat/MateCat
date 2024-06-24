import React, {useEffect, useRef} from 'react'
import {
  fireEvent,
  render,
  renderHook,
  screen,
  waitFor,
} from '@testing-library/react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {
  ANALYSIS_BREAKDOWNS,
  ANALYSIS_SCHEMA_KEYS,
  AnalysisTab,
} from './AnalysisTab'
import projectTemplatesMock from '../../../../../../../mocks/projectTemplateMock'
import {mswServer} from '../../../../../../../mocks/mswServer'
import {http, HttpResponse} from 'msw'
import payableRateTemplateMock from '../../../../../../../mocks/payableRateTemplateMock'
import useTemplates from '../../../../hooks/useTemplates'
import languagesMock from '../../../../../../../mocks/languagesMock'
import {CreateProjectContext} from '../../../createProject/CreateProjectContext'

const wrapperElement = document.createElement('div')
const projectContext = {
  languages: languagesMock.map((lang) => {
    return {...lang, id: lang.code}
  }),
}
const WrapperComponent = (contextProps) => {
  const ref = useRef()

  useEffect(() => {
    ref.current.appendChild(wrapperElement)
  }, [])

  return (
    <CreateProjectContext.Provider value={projectContext}>
      <SettingsPanelContext.Provider
        value={{...contextProps, portalTarget: wrapperElement}}
      >
        <div ref={ref}>
          <AnalysisTab />
        </div>
      </SettingsPanelContext.Provider>
    </CreateProjectContext.Provider>
  )
}

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

test('Render Analysis Tab', async () => {
  const {result} = renderHook(() => useTemplates(ANALYSIS_SCHEMA_KEYS))
  let contextProps = {
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: jest.fn(),
    currentProjectTemplate: projectTemplatesMock.items[0],
    projectTemplates: projectTemplatesMock.items,
    analysisTemplates: result.current,
    portalTarget: wrapperElement,
  }
  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  await waitFor(() => expect(result.current.templates.length).not.toBe(0))
  contextProps.analysisTemplates = result.current
  contextProps.analysisTemplates.modifyingCurrentTemplate = jest.fn()
  rerender(<WrapperComponent {...{...contextProps}} />)
  const currentAnalysisTemplate = result.current.templates?.find(
    ({id, isTemporary}) =>
      id === result.current.currentTemplate.id && !isTemporary,
  )
  expect(screen.getByText('Repetitions')).toBeInTheDocument()

  for (const [key, value] of Object.entries(ANALYSIS_BREAKDOWNS)) {
    if (value !== '100%_PUBLIC' && value !== 'ICE') {
      const valuePerc = currentAnalysisTemplate.breakdowns.default[value] + '%'
      expect(screen.queryByTestId(ANALYSIS_BREAKDOWNS[key]).value).toBe(
        valuePerc,
      )
    }
  }
  const mtValue = screen.getByTestId(ANALYSIS_BREAKDOWNS.mt)
  fireEvent.change(mtValue, {target: {value: 100}})
  expect(screen.queryByTestId(ANALYSIS_BREAKDOWNS.mt).value).toBe('100')
  fireEvent.blur(mtValue)
  expect(
    contextProps.analysisTemplates.modifyingCurrentTemplate,
  ).toBeCalledTimes(1)
  expect(mtValue).not.toHaveClass('analysis-value-not-saved')
})

test('Modify template breakdowns', async () => {
  const {result} = renderHook(() => useTemplates(ANALYSIS_SCHEMA_KEYS))
  let contextProps = {
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: jest.fn(),
    currentProjectTemplate: projectTemplatesMock.items[0],
    projectTemplates: projectTemplatesMock.items,
    analysisTemplates: result.current,
  }
  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  await waitFor(() => expect(result.current.templates.length).not.toBe(0))
  contextProps.analysisTemplates = result.current
  contextProps.analysisTemplates.modifyingCurrentTemplate = jest.fn()

  rerender(<WrapperComponent {...{...contextProps}} />)
  const mtValue = screen.getByTestId(ANALYSIS_BREAKDOWNS.mt)
  fireEvent.change(mtValue, {target: {value: 100}})
  expect(screen.queryByTestId(ANALYSIS_BREAKDOWNS.mt).value).toBe('100')
  fireEvent.blur(mtValue)
  expect(
    contextProps.analysisTemplates.modifyingCurrentTemplate,
  ).toBeCalledTimes(1)
})

test('Change template', async () => {
  const {result} = renderHook(() => useTemplates(ANALYSIS_SCHEMA_KEYS))
  let contextProps = {
    openLoginModal: jest.fn(),
    modifyingCurrentTemplate: jest.fn(),
    currentProjectTemplate: projectTemplatesMock.items[0],
    projectTemplates: projectTemplatesMock.items,
    analysisTemplates: result.current,
    portalTarget: wrapperElement,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  await waitFor(() => expect(result.current.templates.length).not.toBe(0))
  contextProps.analysisTemplates.modifyingCurrentTemplate = jest.fn()
  contextProps.analysisTemplates = result.current
  rerender(<WrapperComponent {...{...contextProps}} />)
  const select = screen.getByText('Default')
  fireEvent.click(select)
  const templateDropdown = screen.getByText(
    contextProps.analysisTemplates.templates[1].payable_rate_template_name,
  )
  expect(templateDropdown).toBeInTheDocument()
  fireEvent.click(templateDropdown)

  contextProps.analysisTemplates = result.current

  rerender(<WrapperComponent {...{...contextProps}} />)
  const currentAnalysisTemplate = result.current.templates?.find(
    ({id, isTemporary}) =>
      id === result.current.currentTemplate.id && !isTemporary,
  )
  expect(currentAnalysisTemplate.payable_rate_template_name).toBe(
    contextProps.analysisTemplates.templates[1].payable_rate_template_name,
  )
  for (const [key, value] of Object.entries(ANALYSIS_BREAKDOWNS)) {
    if (value !== '100%_PUBLIC' && value !== 'ICE') {
      const valuePerc = currentAnalysisTemplate.breakdowns.default[value] + '%'
      expect(screen.queryByTestId(ANALYSIS_BREAKDOWNS[key]).value).toBe(
        valuePerc,
      )
    }
  }
  expect(screen.getByText('French')).toBeInTheDocument()
  expect(screen.getByText('Italian')).toBeInTheDocument()
  expect(screen.getByText('Azerbaijani')).toBeInTheDocument()
  expect(screen.getByText('Austrian German')).toBeInTheDocument()
})
