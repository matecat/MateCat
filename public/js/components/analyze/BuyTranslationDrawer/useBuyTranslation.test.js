import {act, renderHook} from '@testing-library/react'
import CommonUtils from '../../../utils/commonUtils'
import {CONTACT_US_URL} from '../OutsourceOfferCard'
import {useBuyTranslation} from './useBuyTranslation'

const project = {
  id: 20,
  password: 'projpass',
  jobs: [{id: 102, password: 'frpass', outsource: null}],
}
const job = {id: 102, outsourceAvailable: true}
const chunk = {password: 'frpass', totals: {equivalent: 100}}

describe('useBuyTranslation', () => {
  beforeEach(() => {
    jest.spyOn(window, 'open').mockImplementation(() => null)
    jest.spyOn(CommonUtils, 'dispatchAnalyticsEvents').mockImplementation()
  })

  afterEach(() => jest.restoreAllMocks())

  test('opens the order for the project job and tracks the request', () => {
    const onOpen = jest.fn()
    const {result} = renderHook(() => useBuyTranslation({project, onOpen}))

    act(() => result.current.openBuy(job, chunk))
    expect(result.current.order).toEqual({
      job,
      chunk,
      projectJob: project.jobs[0],
    })
    expect(onOpen).toHaveBeenCalledWith(job, chunk)
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
      event: 'outsource_request',
    })

    act(() => result.current.closeBuy())
    expect(result.current.order).toBeNull()
  })

  test('a job Translated cannot sell goes to the contact page', () => {
    const {result} = renderHook(() => useBuyTranslation({project}))
    act(() =>
      result.current.openBuy({...job, outsourceAvailable: false}, chunk),
    )
    expect(window.open).toHaveBeenCalledWith(CONTACT_US_URL, '_blank')
    expect(result.current.order).toBeNull()
    expect(CommonUtils.dispatchAnalyticsEvents).not.toHaveBeenCalled()
  })
})
