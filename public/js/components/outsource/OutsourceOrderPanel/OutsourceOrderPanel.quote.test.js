import React from 'react'
import {fireEvent, render, screen, within} from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import {fromJS} from 'immutable'
import Cookies from 'js-cookie'
import {http, HttpResponse} from 'msw'
import {mswServer} from '../../../../mocks/mswServer'
import {clearOutsourceQuoteCache} from '../../../hooks/useOutsourceQuote'
import {QUOTE_NOT_AVAILABLE_MESSAGE} from '../outsourceConstants'
import {OutsourceOrderPanel} from './OutsourceOrderPanel'

// The real quote hook, driven by the vendor's responses

// jsdom has no ResizeObserver; the currency menu (Radix) needs one
global.ResizeObserver = class ResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}

const QUOTE_URL = 'http://localhost/api/app/outsource-to'

const quote = (overrides = {}) => ({
  data: [
    [
      {
        id: 99,
        quote_available: '1',
        quote_result: '1',
        outsourced: '0',
        typeOfService: 'professional',
        t_name: 'Kada',
        t_experience_years: '1',
        t_words_total: 860871,
        words: 59,
        price: '8.80',
        r_price: '8.80',
        currency: 'EUR',
        delivery: '2099-01-10T10:00:00.000Z',
        r_delivery: '2099-01-11T10:00:00.000Z',
        ...overrides,
      },
    ],
  ],
  return_url: {url_ok: 'http://ok', url_ko: 'http://ko', confirm_urls: []},
})

let responses
let requests
const serveQuotes = () =>
  mswServer.use(
    http.post(QUOTE_URL, () => {
      requests += 1
      const next = responses.length > 1 ? responses.shift() : responses[0]
      return next()
    }),
  )

const job = fromJS({
  id: 396,
  password: 'chunkpass',
  source: 'en-US',
  target: 'fr-FR',
  outsource: null,
})
const project = fromJS({id: 394, password: 'projpass'})

const renderPanel = () =>
  render(
    <OutsourceOrderPanel
      job={job}
      project={project}
      standardWC={59}
      outsourceEnabled={true}
    />,
  )

describe('OutsourceOrderPanel with the vendor responses', () => {
  beforeEach(() => {
    requests = 0
    clearOutsourceQuoteCache()
    global.config = {
      ...global.config,
      basepath: 'http://localhost/',
      enableMultiDomainApi: false,
    }
    // Rates already known: no exchange-rate request
    Cookies.set('matecat_changeRates', JSON.stringify({EUR: 1, USD: 1.5}))
    Cookies.set('matecat_currency', 'EUR')
  })

  afterEach(() => {
    mswServer.resetHandlers()
    Cookies.remove('matecat_changeRates')
    Cookies.remove('matecat_currency')
  })

  test('a quote that failed to compute can be retried', async () => {
    responses = [
      () => HttpResponse.json(quote({quote_result: '0'})),
      () => HttpResponse.json(quote()),
    ]
    serveQuotes()
    renderPanel()

    expect(
      await screen.findByText('We could not get a quote.'),
    ).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', {name: 'Retry'}))
    expect(await screen.findByText('Kada')).toBeInTheDocument()
    expect(requests).toBe(2)
  })

  test('a failed request can be retried', async () => {
    responses = [
      () => new HttpResponse(null, {status: 500}),
      () => HttpResponse.json(quote()),
    ]
    serveQuotes()
    renderPanel()

    fireEvent.click(await screen.findByRole('button', {name: 'Retry'}))
    expect(await screen.findByText('Kada')).toBeInTheDocument()
  })

  test('no quote at all explains it, without a retry', async () => {
    responses = [() => HttpResponse.json(quote({quote_available: '0'}))]
    serveQuotes()
    renderPanel()

    expect(
      await screen.findByText(QUOTE_NOT_AVAILABLE_MESSAGE),
    ).toBeInTheDocument()
    expect(
      screen.queryByRole('button', {name: 'Retry'}),
    ).not.toBeInTheDocument()
  })

  test('a faster date the vendor cannot meet keeps the form and the quote', async () => {
    responses = [
      () => HttpResponse.json(quote()),
      () => HttpResponse.json(quote({quote_available: '0'})),
    ]
    serveQuotes()
    renderPanel()

    await screen.findByText('Kada')
    fireEvent.click(screen.getByRole('button', {name: 'Need it faster?'}))
    fireEvent.click(screen.getByRole('button', {name: 'Get price'}))

    expect(
      await screen.findByText('The deadline is too close: pick another one.'),
    ).toBeInTheDocument()
    // Still the order form, with the previous quote
    expect(screen.getByText('Kada')).toBeInTheDocument()
    expect(screen.getByRole('button', {name: 'Need it faster?'})).toBeVisible()
    expect(requests).toBe(2)
  })

  test('adding a revision asks for a premium quote and adds its price', async () => {
    const services = []
    mswServer.use(
      http.post(QUOTE_URL, async ({request}) => {
        const service = (await request.formData()).get('typeOfService')
        services.push(service)
        return HttpResponse.json(
          quote({typeOfService: service, price: '8.80', r_price: '4.20'}),
        )
      }),
    )
    renderPanel()

    await screen.findByText('Kada')
    expect(screen.queryByText('€ 13.00')).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('checkbox', {name: /Add revision/}))

    expect(await screen.findByText('€ 13.00')).toBeInTheDocument()
    expect(services).toEqual(['professional', 'premium'])
  })

  test('the per-word price switches the currency of every price', async () => {
    responses = [() => HttpResponse.json(quote())]
    serveQuotes()
    renderPanel()
    const setCookie = jest.spyOn(Cookies, 'set')
    await screen.findByText('Kada')
    expect(screen.getByText('+ € 8.80')).toBeInTheDocument()

    await userEvent.click(
      screen.getByRole('button', {name: 'Change currency (now EUR)'}),
    )
    await userEvent.click(
      within(await screen.findByRole('menu')).getByText('US dollar (USD)'),
    )

    // The choice is stored where every quote reads it: a secure cookie, which
    // jsdom on http://localhost does not keep, so the conversion itself is
    // checked in the browser. Here: every price takes the new currency
    expect(setCookie).toHaveBeenCalledWith('matecat_currency', 'USD', {
      secure: true,
    })
    expect(await screen.findByText(/^\+ US\$ /)).toBeInTheDocument()
    expect(screen.getAllByText(/^US\$ \d/)).toHaveLength(2)
    expect(
      screen.getByRole('button', {name: 'Change currency (now USD)'}),
    ).toHaveTextContent(/^= US\$ [\d.]+ \/ word$/)
    expect(screen.queryByText(/€/)).not.toBeInTheDocument()
  })
})
