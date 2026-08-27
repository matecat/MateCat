import React from 'react'
import {render, screen, fireEvent} from '@testing-library/react'
import {fromJS} from 'immutable'
import Cookies from 'js-cookie'

import OutsourceVendor from './OutsourceVendor'
import useOutsourceQuote from '../../hooks/useOutsourceQuote'
import useCurrencyRates from '../../hooks/useCurrencyRates'
import UserStore from '../../stores/UserStore'
import CommonUtils from '../../utils/commonUtils'

jest.mock('../../hooks/useOutsourceQuote')
jest.mock('../../hooks/useCurrencyRates')
jest.mock('../../stores/UserStore', () => ({
  getUser: jest.fn(),
}))

jest.mock('./OutsourceInfo', () => () => <div>outsource-info</div>)
jest.mock('./components/OutsourceLoader', () => (props) => (
  <div>
    loader:
    {props.translatorsNumber ? props.translatorsNumber.printable : 'none'}
  </div>
))
jest.mock('./components/ServiceBox', () => (props) => (
  <div>service-box:{String(props.revision)}</div>
))
jest.mock('./components/TranslatorDetails', () => (props) => (
  <div>
    translator-details:{props.translatedWords}:{props.priceCurrencySymbol}
  </div>
))
jest.mock('./components/RevisionCheckbox', () => (props) => (
  <div>
    revision-checkbox:{String(props.revision)}
    <button onClick={props.onToggle}>toggle-revision</button>
  </div>
))
jest.mock('./components/DeliverySection', () => (props) => (
  <div>
    delivery-section
    <button onClick={() => props.onChangeTimezone('3')}>change-timezone</button>
    <button onClick={props.onToggleNeedItFaster}>toggle-need-it-faster</button>
    <button onClick={props.onGetNewRates}>get-new-rates</button>
  </div>
))
jest.mock('./components/OrderBox', () => (props) => (
  <div>
    order-box:{props.price}
    <button onClick={props.onSendOutsource}>send-outsource</button>
    <button onClick={props.onOpenOutsourcePage}>open-outsource-page</button>
    <button onClick={() => props.onCurrencyChange('USD')}>
      change-currency
    </button>
  </div>
))

const baseCurrencyHook = {
  getCurrentCurrency: jest.fn(() => 'EUR'),
  getCurrencyPrice: jest.fn((price) => price),
  getPriceCurrencySymbol: jest.fn(() => '€'),
  onCurrencyChange: jest.fn(),
}

const makeQuoteHook = (overrides = {}) => ({
  outsource: false,
  setOutsource: jest.fn(),
  revision: false,
  chunkQuote: null,
  setChunkQuote: jest.fn(),
  outsourceConfirmed: false,
  jobOutsourced: false,
  quoteNotAvailable: false,
  errorQuote: false,
  errorOutsource: false,
  deliveryDate: new Date('2026-07-20T00:00:00.000Z'),
  setDeliveryDate: jest.fn(),
  selectedTime: '14',
  setSelectedTime: jest.fn(),
  quoteResponseRef: {current: []},
  urlOkRef: {current: 'url-ok'},
  urlKoRef: {current: 'url-ko'},
  confirmUrlsRef: {current: 'confirm-urls'},
  dataKeyRef: {current: 'data-key'},
  selectedDateRef: {current: null},
  fetchQuote: jest.fn(),
  toggleRevision: jest.fn(),
  updateTimezoneRef: jest.fn(),
  getDeliveryDateFromQuote: jest.fn(() => ({
    day: '20',
    month: 'Jul',
    time: '2:00 PM',
  })),
  checkChosenDateIsAfter: jest.fn(() => false),
  ...overrides,
})

const baseJob = fromJS({outsource: null})

const baseProps = {
  job: baseJob,
  project: fromJS({id: 1}),
  extendedView: true,
  standardWC: 100,
  translatorsNumber: {asInt: 10, printable: '10'},
}

describe('OutsourceVendor', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    Cookies.set('matecat_timezone', '2')
    global.config = {...global.config, outsource_service_login: 'http://login'}
    useCurrencyRates.mockReturnValue(baseCurrencyHook)
    UserStore.getUser.mockReturnValue({user: {email: 'user@translated.net'}})
  })

  test('shows the quote-not-available message when errorOutsource is true', () => {
    useOutsourceQuote.mockReturnValue(makeQuoteHook({errorOutsource: true}))
    render(<OutsourceVendor {...baseProps} />)
    expect(
      screen.getByText(/Quote not available, please contact us/),
    ).toBeInTheDocument()
    expect(screen.queryByText('outsource-info')).not.toBeInTheDocument()
  })

  test('renders the loader in extended view while the outsource quote is not ready', () => {
    useOutsourceQuote.mockReturnValue(makeQuoteHook({outsource: false}))
    render(<OutsourceVendor {...baseProps} />)
    expect(screen.getByText('loader:10')).toBeInTheDocument()
    expect(screen.getByText('service-box:false')).toBeInTheDocument()
    expect(screen.getByText('outsource-info')).toBeInTheDocument()
  })

  test('renders full extended details once the quote is available', () => {
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({outsource: true, chunkQuote, revision: true}),
    )
    render(<OutsourceVendor {...baseProps} />)

    expect(screen.getByText('service-box:true')).toBeInTheDocument()
    expect(screen.getByText(/translator-details/)).toBeInTheDocument()
    expect(screen.getByText(/revision-checkbox:true/)).toBeInTheDocument()
    expect(screen.getByText('delivery-section')).toBeInTheDocument()
    expect(screen.getByText(/order-box/)).toBeInTheDocument()
  })

  test('hides the order box when the quote errored, even with an outsource available', () => {
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({outsource: true, chunkQuote, errorQuote: true}),
    )
    render(<OutsourceVendor {...baseProps} />)
    expect(screen.getByText('delivery-section')).toBeInTheDocument()
    expect(screen.queryByText(/order-box/)).not.toBeInTheDocument()
  })

  test('renders the compact view when extendedView is false and lets the user expand it', () => {
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({outsource: true, chunkQuote}),
    )
    render(<OutsourceVendor {...baseProps} extendedView={false} />)

    expect(screen.getByText('Let us do it for you')).toBeInTheDocument()
    expect(screen.getByText('delivery-section')).toBeInTheDocument()
    expect(screen.queryByText('service-box:false')).not.toBeInTheDocument()

    fireEvent.click(screen.getByText('+ View More'))
    expect(screen.getByText('service-box:false')).toBeInTheDocument()
  })

  test('renders the loader in compact view while the outsource quote is not ready', () => {
    useOutsourceQuote.mockReturnValue(makeQuoteHook({outsource: false}))
    render(<OutsourceVendor {...baseProps} extendedView={false} />)
    expect(screen.getByText('loader:10')).toBeInTheDocument()
  })

  test('computes the price from the already-outsourced job when present', () => {
    const job = fromJS({outsource: {price: '150.55'}})
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({outsource: true, chunkQuote}),
    )
    render(<OutsourceVendor {...baseProps} job={job} />)
    expect(screen.getByText('order-box:150.55')).toBeInTheDocument()
  })

  test('computes the price from the chunk quote, including revision price', () => {
    const chunkQuote = fromJS({price: '100', r_price: '25', t_words_total: 500})
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({outsource: true, chunkQuote, revision: true}),
    )
    render(<OutsourceVendor {...baseProps} />)
    expect(screen.getByText('order-box:125')).toBeInTheDocument()
  })

  test('forwards the currency change to the shared hook handler', () => {
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    const setChunkQuote = jest.fn()
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({outsource: true, chunkQuote, setChunkQuote}),
    )
    render(<OutsourceVendor {...baseProps} />)

    fireEvent.click(screen.getByText('change-currency'))
    expect(baseCurrencyHook.onCurrencyChange).toHaveBeenCalledWith(
      'USD',
      chunkQuote,
      setChunkQuote,
    )
  })

  test('submits the hidden form and dispatches an analytics event on send', () => {
    const dispatchSpy = jest.spyOn(CommonUtils, 'dispatchAnalyticsEvents')
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({outsource: true, chunkQuote}),
    )
    HTMLFormElement.prototype.submit = jest.fn()

    render(<OutsourceVendor {...baseProps} />)
    fireEvent.click(screen.getByText('send-outsource'))

    expect(HTMLFormElement.prototype.submit).toHaveBeenCalled()
    expect(dispatchSpy).toHaveBeenCalledWith(
      expect.objectContaining({event: 'outsource_clicked'}),
    )
    dispatchSpy.mockRestore()
  })

  test('opens the outsource review page in a new tab', () => {
    const job = fromJS({
      outsource: {price: '100', quote_review_link: 'https://review.link'},
    })
    const openSpy = jest.spyOn(window, 'open').mockImplementation(() => {})
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({outsource: true, chunkQuote}),
    )

    render(<OutsourceVendor {...baseProps} job={job} />)
    fireEvent.click(screen.getByText('open-outsource-page'))

    expect(openSpy).toHaveBeenCalledWith('https://review.link', '_blank')
    openSpy.mockRestore()
  })

  test('persists the chosen timezone to a cookie and updates the hook ref', () => {
    const updateTimezoneRef = jest.fn()
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({outsource: true, chunkQuote, updateTimezoneRef}),
    )
    const setSpy = jest.spyOn(Cookies, 'set')
    render(<OutsourceVendor {...baseProps} />)

    fireEvent.click(screen.getByText('change-timezone'))
    expect(setSpy).toHaveBeenCalledWith(
      'matecat_timezone',
      '3',
      expect.objectContaining({secure: true}),
    )
    expect(updateTimezoneRef).toHaveBeenCalledWith('3')
    setSpy.mockRestore()
  })

  test('toggles the need-it-faster flag through the delivery section', () => {
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({outsource: true, chunkQuote}),
    )
    render(<OutsourceVendor {...baseProps} />)
    expect(() =>
      fireEvent.click(screen.getByText('toggle-need-it-faster')),
    ).not.toThrow()
  })

  test('flags a past delivery date as an error instead of fetching a quote', () => {
    const fetchQuote = jest.fn()
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({
        outsource: true,
        chunkQuote,
        fetchQuote,
        deliveryDate: new Date('2000-01-01T00:00:00.000Z'),
        selectedTime: '10',
      }),
    )
    render(<OutsourceVendor {...baseProps} />)

    fireEvent.click(screen.getByText('get-new-rates'))
    expect(fetchQuote).not.toHaveBeenCalled()
  })

  test('fetches a new quote for a future delivery date', () => {
    const fetchQuote = jest.fn()
    const setOutsource = jest.fn()
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    const futureDate = new Date(Date.now() + 365 * 24 * 60 * 60 * 1000)
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({
        outsource: true,
        chunkQuote,
        fetchQuote,
        setOutsource,
        deliveryDate: futureDate,
        selectedTime: '10',
      }),
    )
    render(<OutsourceVendor {...baseProps} />)

    fireEvent.click(screen.getByText('get-new-rates'))
    expect(fetchQuote).toHaveBeenCalled()
    expect(setOutsource).toHaveBeenCalledWith(false)
  })

  test('toggles the revision checkbox through the shared hook handler', () => {
    const toggleRevision = jest.fn()
    const chunkQuote = fromJS({price: '100', r_price: '20', t_words_total: 500})
    useOutsourceQuote.mockReturnValue(
      makeQuoteHook({outsource: true, chunkQuote, toggleRevision}),
    )
    render(<OutsourceVendor {...baseProps} />)

    fireEvent.click(screen.getByText('toggle-revision'))
    expect(toggleRevision).toHaveBeenCalled()
  })
})
