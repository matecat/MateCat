import {act, renderHook} from '@testing-library/react'
import CommonUtils from '../../../utils/commonUtils'
import {CONTACT_US_URL} from '../OutsourceOfferCard'
import {useBuyTranslation} from './useBuyTranslation'

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
    const {result} = renderHook(() => useBuyTranslation({onOpen}))

    act(() => result.current.openBuy(job, chunk))
    expect(result.current.order).toEqual({job, chunk})
    expect(onOpen).toHaveBeenCalledWith(job, chunk)
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith({
      event: 'outsource_request',
    })

    act(() => result.current.closeBuy())
    expect(result.current.order).toBeNull()
  })

  test('a job Translated cannot sell goes to the contact page', () => {
    const {result} = renderHook(() => useBuyTranslation())
    act(() =>
      result.current.openBuy({...job, outsourceAvailable: false}, chunk),
    )
    expect(window.open).toHaveBeenCalledWith(CONTACT_US_URL, '_blank')
    expect(result.current.order).toBeNull()
    expect(CommonUtils.dispatchAnalyticsEvents).not.toHaveBeenCalled()
  })

  test('a whole split job opens on the chunk picker, then takes a chunk', () => {
    const onOpen = jest.fn()
    const {result} = renderHook(() => useBuyTranslation({onOpen}))

    act(() => result.current.openBuy(job, null))
    expect(result.current.order).toEqual({job, chunk: null})
    expect(onOpen).toHaveBeenLastCalledWith(job, null)

    act(() => result.current.pickChunk(chunk))
    expect(result.current.order).toEqual({job, chunk})
    expect(onOpen).toHaveBeenLastCalledWith(job, chunk)
    // Tracked once, when the drawer opened
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledTimes(1)
  })
})
