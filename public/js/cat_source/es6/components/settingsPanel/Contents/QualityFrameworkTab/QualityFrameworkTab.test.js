import React, {useEffect, useRef} from 'react'
import {SettingsPanelContext} from '../../SettingsPanelContext'
import {QF_SCHEMA_KEYS, QualityFrameworkTab} from './QualityFrameworkTab'
import {mswServer} from '../../../../../../../mocks/mswServer'
import {HttpResponse, http} from 'msw'
import qaModelTemplateMocks from '../../../../../../../mocks/qaModelTemplateMocks'
import projectTemplateMock from '../../../../../../../mocks/projectTemplateMock'
import {act, render, renderHook, screen, waitFor} from '@testing-library/react'
import useTemplates from '../../../../hooks/useTemplates'
import userEvent from '@testing-library/user-event'

global.config = {
  basepath: 'http://localhost/',
  enableMultiDomainApi: false,
  ajaxDomainsNumber: 20,
  isLoggedIn: 1,
}

const wrapperElement = document.createElement('div')
const WrapperComponent = (contextProps) => {
  const ref = useRef()

  useEffect(() => {
    ref.current.appendChild(wrapperElement)
  }, [])

  return (
    <SettingsPanelContext.Provider
      value={{...contextProps, portalTarget: wrapperElement}}
    >
      <div ref={ref}>
        <QualityFrameworkTab />
      </div>
    </SettingsPanelContext.Provider>
  )
}

beforeEach(() => {
  mswServer.use(
    http.get(`${config.basepath}api/v3/qa_model_template`, () => {
      return HttpResponse.json(qaModelTemplateMocks)
    }),
  )
})

test('Render properly and change ept thresholds', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }

  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  const R1Input = screen.getByTestId('threshold-R1')

  expect(R1Input.value).toBe('20')
  expect(screen.getByTestId('threshold-R2').value).toBe('15')

  await user.click(R1Input)

  await act(async () => user.keyboard('3'))
  refresh()
  await act(async () => user.keyboard('1'))
  refresh()

  expect(screen.getByTestId('save-as-new-template')).toBeInTheDocument()

  refresh()

  expect(R1Input.value).toBe('31')
})

test('Change template', async () => {
  const user = userEvent.setup()

  const {result} = renderHook(() => useTemplates(QF_SCHEMA_KEYS))

  let currentProjectTemplate = projectTemplateMock.items[0]

  const contextProps = {
    currentProjectTemplate,
    modifyingCurrentTemplate: (value) => {
      currentProjectTemplate = value(currentProjectTemplate)
    },
    qualityFrameworkTemplates: result.current,
  }

  const {rerender} = render(<WrapperComponent {...{...contextProps}} />)
  const refresh = () => {
    contextProps.qualityFrameworkTemplates = result.current
    rerender(<WrapperComponent {...{...contextProps}} />)
  }

  await waitFor(() => expect(result.current.templates).not.toBe(0))
  refresh()

  await user.click(screen.getByText('Default'))

  const templateDropDownItem = screen.getByText('QF T1')
  expect(templateDropDownItem).toBeInTheDocument()

  await user.click(templateDropDownItem)
  refresh()

  const R1Input = screen.getByTestId('threshold-R1')

  expect(R1Input.value).toBe('28')
  expect(screen.getByTestId('threshold-R2').value).toBe('2')

  await user.click(R1Input)

  await act(async () => user.keyboard('1'))
  refresh()
  await act(async () => user.keyboard('2'))
  refresh()

  expect(R1Input.value).toBe('12')
  expect(R1Input).toHaveClass('quality-framework-not-saved')

  const saveAsChanges = screen.getByTestId('save-as-changes')
  expect(saveAsChanges).toBeInTheDocument()
  expect(screen.getByTestId('save-as-new-template')).toBeInTheDocument()

  mswServer.use(
    http.put(`${config.basepath}api/v3/qa_model_template/:id`, () => {
      const {isTemporary, ...data} = result.current.currentTemplate //eslint-disable-line
      return HttpResponse.json(data)
    }),
  )

  await user.click(saveAsChanges)
  refresh()

  expect(R1Input).not.toHaveClass('quality-framework-not-saved')
})
