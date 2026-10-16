import React from 'react'
import {fireEvent, render, screen} from '@testing-library/react'
import {fromJS} from 'immutable'
import Cookies from 'js-cookie'
import useOutsourceQuote, {
  evictOutsourceQuoteCache,
} from '../../../hooks/useOutsourceQuote'
import useCurrencyRates from '../../../hooks/useCurrencyRates'
import CommonUtils from '../../../utils/commonUtils'
import {OutsourceOrderPanel} from './OutsourceOrderPanel'

jest.mock('../../../hooks/useOutsourceQuote')
jest.mock('../../../hooks/useCurrencyRates')
jest.mock('../GMTSelect', () => ({
  GMTSelect: ({changeValue}) => (
    <button type="button" onClick={() => changeValue('3')}>
      Timezone
    </button>
  ),
}))

const chunkQuote = fromJS({
  t_name: 'Kada',
  t_experience_years: '1',
  t_words_total: 860871,
  words: 59,
  price: '8.80',
  r_price: '8.80',
  currency: 'EUR',
  delivery: '2026-10-14 13:00:00',
})

const makeQuote = (overrides = {}) => ({
  status: 'ready',
  retry: jest.fn(),
  outsource: true,
  setOutsource: jest.fn(),
  revision: false,
  chunkQuote,
  setChunkQuote: jest.fn(),
  outsourceConfirmed: false,
  jobOutsourced: false,
  quoteNotAvailable: false,
  errorQuote: false,
  errorOutsource: false,
  deliveryDate: new Date('2026-10-14T13:00:00.000Z'),
  setDeliveryDate: jest.fn(),
  selectedTime: '13',
  setSelectedTime: jest.fn(),
  quoteResponseRef: {current: [{}]},
  urlOkRef: {current: 'http://ok'},
  urlKoRef: {current: 'http://ko'},
  confirmUrlsRef: {current: 'http://confirm'},
  dataKeyRef: {current: 'key-1'},
  selectedDateRef: {current: null},
  fetchQuote: jest.fn(),
  toggleRevision: jest.fn(),
  updateTimezoneRef: jest.fn(),
  getDeliveryDateFromQuote: jest.fn(() => ({
    day: '14',
    month: 'Oct',
    time: '01:00 PM',
  })),
  checkChosenDateIsAfter: jest.fn(() => false),
  ...overrides,
})

const job = fromJS({
  id: 396,
  password: 'chunkpass',
  source: 'en-US',
  target: 'fr-FR',
  outsource: null,
})
const project = fromJS({id: 394, password: 'projpass'})

const renderPanel = (quote = {}, props = {}) => {
  useOutsourceQuote.mockReturnValue(makeQuote(quote))
  return render(
    <OutsourceOrderPanel
      job={job}
      project={project}
      standardWC={59}
      serviceLoginUrl="http://login"
      outsourceEnabled={true}
      {...props}
    />,
  )
}

describe('OutsourceOrderPanel', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    Cookies.set('matecat_timezone', '2')
    useCurrencyRates.mockReturnValue({
      getCurrentCurrency: () => 'EUR',
      getCurrencyPrice: (price) => Number(price).toFixed(2),
      getPriceCurrencySymbol: () => '€',
      onCurrencyChange: jest.fn(),
    })
    jest.spyOn(CommonUtils, 'dispatchAnalyticsEvents').mockImplementation()
    jest.spyOn(CommonUtils, 'dispatchCustomEvent').mockImplementation()
    jest.spyOn(HTMLFormElement.prototype, 'submit').mockImplementation(() => {})
  })

  afterEach(() => {
    jest.restoreAllMocks()
    Cookies.remove('matecat_timezone')
  })

  test('without a named translator it explains who translates', () => {
    renderPanel({chunkQuote: chunkQuote.set('t_name', '')})
    expect(
      screen.getByText(/Translated uses the most qualified translator/),
    ).toBeInTheDocument()
  })

  test('"Need it faster?" opens the date form; Close goes back', () => {
    renderPanel()
    fireEvent.click(screen.getByRole('button', {name: 'Need it faster?'}))
    expect(screen.getByRole('button', {name: 'Get price'})).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', {name: 'Close'}))
    expect(
      screen.queryByRole('button', {name: 'Get price'}),
    ).not.toBeInTheDocument()
  })

  test('a later date asks for a new quote', () => {
    const quote = makeQuote({deliveryDate: new Date('2099-01-01T00:00:00Z')})
    useOutsourceQuote.mockReturnValue(quote)
    render(
      <OutsourceOrderPanel
        job={job}
        project={project}
        standardWC={59}
        outsourceEnabled={true}
      />,
    )
    fireEvent.click(screen.getByRole('button', {name: 'Need it faster?'}))
    fireEvent.click(screen.getByRole('button', {name: 'Get price'}))
    expect(quote.fetchQuote).toHaveBeenCalledWith(expect.any(Number))
  })

  test('order now posts the vendor form, evicts the cached quote and tracks it', () => {
    renderPanel()
    fireEvent.click(screen.getByRole('button', {name: 'Order now'}))

    expect(HTMLFormElement.prototype.submit).toHaveBeenCalledTimes(1)
    // The form the order submitted
    const [form] = HTMLFormElement.prototype.submit.mock.contexts
    expect(form).toHaveAttribute('action', 'http://login')
    expect(form).toHaveFormValues({
      url_ok: 'http://ok',
      url_ko: 'http://ko',
      confirm_urls: 'http://confirm',
      data_key: 'key-1',
    })
    expect(evictOutsourceQuoteCache).toHaveBeenCalledWith(396, 'chunkpass')
    expect(CommonUtils.dispatchAnalyticsEvents).toHaveBeenCalledWith(
      expect.objectContaining({event: 'outsource_clicked'}),
    )
  })

  test('an ordered job confirms, then shows its status', () => {
    const {unmount} = renderPanel({outsourceConfirmed: true})
    expect(screen.getByRole('button', {name: 'Confirm'})).toBeInTheDocument()
    expect(screen.getByText('Order sent correctly')).toBeInTheDocument()
    unmount()

    renderPanel(
      {outsourceConfirmed: true, jobOutsourced: true},
      {
        job: job.set(
          'outsource',
          fromJS({
            quote_review_link: 'http://review',
            price: '8.80',
            delivery_date: '2026-10-14 13:00:00',
          }),
        ),
      },
    )
    expect(
      screen.getByRole('button', {name: 'View status'}),
    ).toBeInTheDocument()
  })
})
