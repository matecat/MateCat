import React from 'react'
import {fireEvent, render, screen, waitFor} from '@testing-library/react'
import Cookies from 'js-cookie'
import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../../mocks/mswServer'
import {buildVolumeAnalysis} from '../../../../mocks/volumeAnalysisMock'
import {clearOutsourceQuoteCache} from '../../../hooks/useOutsourceQuote'
import CommonUtils from '../../../utils/commonUtils'
import {deriveAnalysisView} from '../utils/deriveAnalysisView'
import {QUOTE_NOT_AVAILABLE_MESSAGE} from '../../outsource/outsourceConstants'
import {CONTACT_US_URL, OutsourceOfferCard} from './OutsourceOfferCard'

const QUOTE_URL = 'http://localhost/api/app/outsource-to'

const quotePayload = (overrides = {}) => ({
  data: [
    [
      {
        id: 99,
        quote_available: '1',
        quote_result: '1',
        outsourced: '0',
        typeOfService: 'professional',
        price: '100',
        r_price: '20',
        currency: 'EUR',
        delivery: '2026-11-02T10:00:00.000Z',
        r_delivery: '2026-11-03T10:00:00.000Z',
        t_words_total: 100,
        ...overrides,
      },
    ],
  ],
  return_url: {url_ok: 'http://ok', url_ko: 'http://ko', confirm_urls: []},
})

let requests = []
const mockQuote = (respond) =>
  mswServer.use(
    http.post(QUOTE_URL, async ({request}) => {
      const formData = await request.formData()
      const params = {}
      formData.forEach((value, key) => {
        if (/jpassword\]?$/.test(key)) params.jpassword = value
      })
      requests.push(params)
      return respond(params)
    }),
  )

const {jobs} = deriveAnalysisView({analysis: buildVolumeAnalysis()})
const [italian, french] = jobs

const renderCard = (props = {}) => {
  const onBuy = jest.fn()
  const utils = render(
    <OutsourceOfferCard
      projectId={20}
      projectPassword="projpass"
      job={french}
      chunk={french.chunks[0]}
      jobOutsource={null}
      actionsEnabled={true}
      onBuy={onBuy}
      {...props}
    />,
  )
  return {...utils, onBuy}
}

// Longer than the 400ms quote debounce
const waitPastDebounce = () =>
  new Promise((resolve) => setTimeout(resolve, 600))

describe('OutsourceOfferCard', () => {
  beforeEach(() => {
    requests = []
    clearOutsourceQuoteCache()
    global.config = {
      ...global.config,
      basepath: 'http://localhost/',
      enableMultiDomainApi: false,
      enable_outsource: true,
    }
    Cookies.set('matecat_changeRates', JSON.stringify({EUR: 1, USD: 1.2}))
    Cookies.set('matecat_currency', 'EUR')
    Cookies.set('matecat_timezone', '1')
    jest.spyOn(CommonUtils, 'dispatchAnalyticsEvents').mockImplementation()
  })

  afterEach(() => {
    jest.restoreAllMocks()
    mswServer.resetHandlers()
    clearOutsourceQuoteCache()
    Cookies.remove('matecat_changeRates')
    Cookies.remove('matecat_currency')
    Cookies.remove('matecat_timezone')
  })

  test('is hidden when outsourcing is off', async () => {
    global.config.enable_outsource = false
    mockQuote(() => HttpResponse.json(quotePayload()))
    renderCard()

    expect(screen.queryByRole('region')).not.toBeInTheDocument()
    expect(screen.queryByText(/quote/)).not.toBeInTheDocument()
    await waitPastDebounce()
    expect(requests).toHaveLength(0)
  })

  test('shows a loading quote, then the price and delivery date', async () => {
    mockQuote(() => HttpResponse.json(quotePayload()))
    renderCard()

    expect(
      screen.getByRole('heading', {name: 'Getting a quote…'}),
    ).toBeInTheDocument()
    expect(screen.getByRole('status')).toBeInTheDocument()
    expect(
      screen.getByRole('button', {name: 'Buy translation from Translated'}),
    ).toBeDisabled()

    expect(
      await screen.findByRole('heading', {
        name: 'Translated by a professional for € 100.00',
      }),
    ).toBeInTheDocument()
    const delivery = CommonUtils.getGMTDate('2026-11-02T10:00:00.000Z')
    expect(
      screen.getByText(
        `${french.chunks[0].totals.equivalent} words · delivered by ${delivery.day} ${delivery.month} at ${delivery.time} · revision available`,
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', {name: 'Buy translation from Translated'}),
    ).toBeEnabled()
    expect(requests).toEqual([{jpassword: 'frpass'}])
  })

  test('explains an unavailable quote with the contact link', async () => {
    mockQuote(() => HttpResponse.json(quotePayload({quote_available: '0'})))
    renderCard()

    expect(
      await screen.findByText(QUOTE_NOT_AVAILABLE_MESSAGE, {exact: false}),
    ).toBeInTheDocument()
    expect(screen.getByRole('link', {name: 'Contact us'})).toHaveAttribute(
      'href',
      CONTACT_US_URL,
    )
  })

  test('a failed quote can be retried', async () => {
    let fail = true
    mockQuote(() =>
      fail
        ? HttpResponse.json(quotePayload({quote_result: '0'}))
        : HttpResponse.json(quotePayload()),
    )
    renderCard()

    const retry = await screen.findByRole('button', {name: 'Retry'})
    expect(screen.getByText('We could not get a quote.')).toBeInTheDocument()

    fail = false
    fireEvent.click(retry)
    expect(
      await screen.findByRole('heading', {name: /for € 100\.00$/}),
    ).toBeInTheDocument()
    expect(requests).toHaveLength(2)
  })

  test('the analysis must be complete before asking for a quote', async () => {
    mockQuote(() => HttpResponse.json(quotePayload()))
    renderCard({actionsEnabled: false})

    expect(
      screen.getByText(
        'The price depends on the weighted word count, so it is ready when the analysis is complete.',
      ),
    ).toBeInTheDocument()
    expect(
      screen.getByRole('button', {name: 'Buy translation from Translated'}),
    ).toBeDisabled()
    await waitPastDebounce()
    expect(requests).toHaveLength(0)
  })

  test('a whole split job is never quoted', async () => {
    mockQuote(() => HttpResponse.json(quotePayload()))
    const {onBuy} = renderCard({job: italian, chunk: null})

    expect(
      screen.getByRole('heading', {name: 'Get this job translated for you'}),
    ).toBeInTheDocument()
    expect(
      screen.getByText(
        'Each of the 2 chunks is bought on its own. To buy the whole job in one order, merge it first.',
      ),
    ).toBeInTheDocument()
    // The drawer opens on the chunk picker
    fireEvent.click(screen.getByRole('button', {name: 'Choose a chunk'}))
    expect(onBuy).toHaveBeenCalledWith(italian, null)
    await waitPastDebounce()
    expect(requests).toHaveLength(0)
  })

  test('a quick change of chunk asks only for the last one', async () => {
    mockQuote(() => HttpResponse.json(quotePayload()))
    const {rerender} = renderCard({job: italian, chunk: italian.chunks[0]})
    rerender(
      <OutsourceOfferCard
        projectId={20}
        projectPassword="projpass"
        job={italian}
        chunk={italian.chunks[1]}
        jobOutsource={null}
        actionsEnabled={true}
        onBuy={jest.fn()}
      />,
    )

    expect(
      await screen.findByRole('heading', {name: /for € 100\.00$/}),
    ).toBeInTheDocument()
    await waitPastDebounce()
    expect(requests).toEqual([{jpassword: 'chunkpass2'}])
  })

  test('buy hands the chunk to the page and fires no event itself', async () => {
    mockQuote(() => HttpResponse.json(quotePayload()))
    const {onBuy} = renderCard()

    await screen.findByRole('heading', {name: /for € 100\.00$/})
    fireEvent.click(
      screen.getByRole('button', {name: 'Buy translation from Translated'}),
    )
    expect(onBuy).toHaveBeenCalledWith(french, french.chunks[0])
    // outsource_request fires when the order form opens (ProjectTreeAnalysis)
    expect(CommonUtils.dispatchAnalyticsEvents).not.toHaveBeenCalled()
  })

  test('a job that cannot be outsourced explains why without a quote', async () => {
    mockQuote(() => HttpResponse.json(quotePayload()))
    renderCard({
      job: {
        ...french,
        outsourceAvailable: false,
        outsourceInfo: {custom_payable_rate: true},
      },
    })

    expect(
      screen.getByText(/custom billing models cannot be outsourced/),
    ).toBeInTheDocument()
    await waitPastDebounce()
    await waitFor(() => expect(requests).toHaveLength(0))
  })

  test('no revision on offer when the vendor quotes none', async () => {
    mockQuote(() => HttpResponse.json(quotePayload({r_price: '0'})))
    renderCard()
    await screen.findByRole('heading', {name: /for € 100\.00$/})
    expect(screen.queryByText(/revision available/)).not.toBeInTheDocument()
  })
})
