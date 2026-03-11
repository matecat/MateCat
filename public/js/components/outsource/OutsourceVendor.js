import React, {useState, useEffect, useRef, useCallback, useMemo} from 'react'
import {fromJS} from 'immutable'
import Cookies from 'js-cookie'
import {isUndefined} from 'lodash'
import {isNull} from 'lodash/lang'
import DatePicker from 'react-datepicker'
import $ from 'jquery'

import OutsourceInfo from './OutsourceInfo'
import {GMTSelect} from './GMTSelect'
import {getOutsourceQuote} from '../../api/getOutsourceQuote'
import {getChangeRates} from '../../api/getChangeRates'
import CommonUtils from '../../utils/commonUtils'
import UserStore from '../../stores/UserStore'

import 'react-datepicker/dist/react-datepicker.css'
import {Select} from '../common/Select'
import {DropdownMenu} from '../common/DropdownMenu/DropdownMenu'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../common/Button/Button'
import HelpCircle from '../../../img/icons/HelpCircle'

// Note 2024-07-08
// I temporary removed RUB and TRY because the Translated API
// does not return the corresponding conversion rates
const currencies = {
  EUR: {symbol: '€', name: 'Euro (EUR)'},
  USD: {symbol: 'US$', name: 'US dollar (USD)'},
  AUD: {symbol: '$', name: 'Australian dollar (AUD)'},
  CAD: {symbol: '$', name: 'Canadian dollar (CAD)'},
  NZD: {symbol: '$', name: 'New Zealand dollar (NZD)'},
  GBP: {symbol: '£', name: 'Pound sterling (GBP)'},
  BRL: {symbol: 'R$', name: 'Real (BRL)'},
  //RUB: {symbol: 'руб', name: 'Russian ruble (RUB)'},
  SEK: {symbol: 'kr', name: 'Swedish krona (SEK)'},
  CHF: {symbol: 'Fr.', name: 'Swiss franc (CHF)'},
  //TRY: {symbol: 'TL', name: 'Turkish lira (TL)'},
  KRW: {symbol: '￦', name: 'Won (KRW)'},
  JPY: {symbol: '￥', name: 'Yen (JPY)'},
  PLN: {symbol: 'zł', name: 'Złoty (PLN)'},
}

const timeOptions = [
  {name: '7:00 AM', id: '7'},
  {name: '8:00 AM', id: '8'},
  {name: '9:00 AM', id: '9'},
  {name: '10:00 AM', id: '10'},
  {name: '11:00 AM', id: '11'},
  {name: '12:00 AM', id: '12'},
  {name: '1:00 PM', id: '13'},
  {name: '2:00 PM', id: '14'},
  {name: '3:00 PM', id: '15'},
  {name: '4:00 PM', id: '16'},
  {name: '5:00 PM', id: '17'},
  {name: '6:00 PM', id: '18'},
  {name: '7:00 PM', id: '19'},
  {name: '8:00 PM', id: '20'},
  {name: '9:00 PM', id: '21'},
]

const numberWithCommas = (x) =>
  x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',')

const OutsourceVendor = ({
  job,
  project,
  extendedView: extendedViewProp,
  standardWC,
  translatorsNumber,
}) => {
  const initialChangeRates = useMemo(() => {
    const stored = Cookies.get('matecat_changeRates')
    return !isUndefined(stored) && !isNull(stored)
      ? $.parseJSON(stored)
      : {}
  }, [])

  const [outsource, setOutsource] = useState(false)
  const [revision, setRevision] = useState(false)
  const [chunkQuote, setChunkQuote] = useState(null)
  const [outsourceConfirmed, setOutsourceConfirmed] = useState(
    !!job.get('outsource'),
  )
  const [extendedView, setExtendedView] = useState(extendedViewProp)
  const [timezone, setTimezone] = useState(Cookies.get('matecat_timezone'))
  const [changeRates, setChangeRates] = useState(initialChangeRates)
  const [jobOutsourced, setJobOutsourced] = useState(!!job.get('outsource'))
  const [errorPastDate, setErrorPastDate] = useState(false)
  const [quoteNotAvailable, setQuoteNotAvailable] = useState(false)
  const [errorQuote, setErrorQuote] = useState(false)
  const [needItFaster, setNeedItFaster] = useState(false)
  const [errorOutsource, setErrorOutsource] = useState(false)
  const [deliveryDate, setDeliveryDate] = useState(() =>
    job && job.get('outsource')
      ? new Date(job.get('outsource').get('delivery_date'))
      : null,
  )
  const [selectedTime, setSelectedTime] = useState('12')

  // Instance variable refs
  const quoteResponseRef = useRef(null)
  const urlOkRef = useRef(null)
  const urlKoRef = useRef(null)
  const confirmUrlsRef = useRef(null)
  const dataKeyRef = useRef(null)
  const selectedDateRef = useRef(null)

  // DOM refs
  const revisionCheckboxRef = useRef(null)
  const outsourceFormRef = useRef(null)

  // Refs to keep latest state for async callbacks
  const revisionRef = useRef(revision)
  const timezoneRef = useRef(timezone)
  useEffect(() => {
    revisionRef.current = revision
  }, [revision])
  useEffect(() => {
    timezoneRef.current = timezone
  }, [timezone])

  const getCurrentCurrency = useCallback(() => {
    const currency = Cookies.get('matecat_currency')
    if (!isUndefined(currency) && !isNull(currency) && currency !== 'null') {
      return currency
    }
    Cookies.set('matecat_currency', 'EUR', {secure: true})
    return 'EUR'
  }, [])

  const getDeliveryDateFromQuote = useCallback(
    (chunkQuoteData, isRevision) => {
      if (!isNull(job.get('outsource'))) {
        return CommonUtils.getGMTDate(
          job.get('outsource').get('delivery_date'),
        )
      } else if (chunkQuoteData) {
        if (isRevision && chunkQuoteData.get('r_delivery')) {
          return CommonUtils.getGMTDate(chunkQuoteData.get('r_delivery'))
        } else {
          return CommonUtils.getGMTDate(chunkQuoteData.get('delivery'))
        }
      }
    },
    [job],
  )

  const fetchOutsourceQuote = useCallback(
    (delivery, revisionType) => {
      let typeOfService = revisionRef.current ? 'premium' : 'professional'
      if (revisionType) typeOfService = revisionType
      const fixedDelivery = delivery ? delivery : ''
      const timezoneToShow = timezoneRef.current
      const currency = getCurrentCurrency()

      getOutsourceQuote(
        project.get('id'),
        project.get('password'),
        job.get('id'),
        job.get('password'),
        fixedDelivery,
        typeOfService,
        timezoneToShow,
        currency,
      )
        .then((quoteData) => {
          if (quoteData.data && quoteData.data.length > 0) {
            if (
              quoteData.data[0][0].quote_available !== '1' &&
              quoteData.data[0][0].outsourced !== '1'
            ) {
              setOutsource(true)
              setQuoteNotAvailable(true)
              return
            } else if (
              quoteData.data[0][0].quote_result !== '1' &&
              quoteData.data[0][0].outsourced !== '1'
            ) {
              setOutsource(true)
              setErrorQuote(true)
              return
            }

            quoteResponseRef.current = quoteData.data[0]
            const chunk = fromJS(quoteData.data[0][0])

            urlOkRef.current = quoteData.return_url.url_ok
            urlKoRef.current = quoteData.return_url.url_ko
            confirmUrlsRef.current = quoteData.return_url.confirm_urls
            dataKeyRef.current = chunk.get('id')

            const isRevision = chunk.get('typeOfService') === 'premium'

            setOutsource(true)
            setQuoteNotAvailable(false)
            setErrorQuote(false)
            setChunkQuote(chunk)
            setRevision(isRevision)
            setJobOutsourced(chunk.get('outsourced') === '1')
            setOutsourceConfirmed(chunk.get('outsourced') === '1')
            setDeliveryDate(new Date(chunk.get('delivery')))

            setTimeout(() => {
              const deliveryStr =
                isRevision && chunk.get('r_delivery')
                  ? chunk.get('r_delivery')
                  : chunk.get('delivery')
              const date = CommonUtils.getGMTDate(deliveryStr)
              if (date?.time2) {
                setSelectedTime(date.time2.split(':')[0])
              }
            })
          } else {
            setOutsource(false)
            setErrorQuote(true)
            setErrorOutsource(true)
          }
        })
        .catch(() => {
          setOutsource(false)
          setErrorQuote(true)
          setErrorOutsource(true)
        })
    },
    [project, job, getCurrentCurrency],
  )

  const retrieveChangeRates = useCallback(() => {
    const stored = Cookies.get('matecat_changeRates')
    if (isUndefined(stored) || isNull(stored) || stored === 'null') {
      getChangeRates().then((response) => {
        const rates = $.parseJSON(response.data)
        if (!isUndefined(rates) && !isNull(stored)) {
          setChangeRates(rates)
          Cookies.set('matecat_changeRates', response.data, {
            expires: 1,
            secure: true,
          })
        }
      })
    }
  }, [])

  // On mount: fetch quote and change rates
  useEffect(() => {
    if (config.enable_outsource) {
      fetchOutsourceQuote()
    }
    retrieveChangeRates()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Sync revision checkbox (replaces componentDidUpdate)
  useEffect(() => {
    if (outsource && extendedView && revisionCheckboxRef.current && chunkQuote) {
      revisionCheckboxRef.current.checked =
        chunkQuote.get('typeOfService') === 'premium'
    }
  }, [outsource, extendedView, chunkQuote])

  const getPriceCurrencySymbol = () => {
    if (outsource && chunkQuote) {
      const currency = chunkQuote.get('currency')
      return currencies[currency]?.symbol ?? ''
    }
    return ''
  }

  const getCurrencyPrice = (price) => {
    const current = getCurrentCurrency()
    if (changeRates) {
      return parseFloat(
        (price * changeRates[current]) / changeRates['EUR'],
      ).toFixed(2)
    }
    return price.toString()
  }

  const changeTimezone = (value) => {
    Cookies.set('matecat_timezone', value, {secure: true})
    setTimezone(value)
  }

  const onCurrencyChange = (value) => {
    Cookies.set('matecat_currency', value, {secure: true})
    setChunkQuote(chunkQuote.set('currency', value))
  }

  const confirmOutsource = () => setOutsourceConfirmed(true)

  const goBack = () => setOutsourceConfirmed(false)

  const sendOutsource = () => {
    quoteResponseRef.current[0] = chunkQuote.toJS()

    $(outsourceFormRef.current)
      .find('input[name=url_ok]')
      .attr('value', urlOkRef.current)
    $(outsourceFormRef.current)
      .find('input[name=url_ko]')
      .attr('value', urlKoRef.current)
    $(outsourceFormRef.current)
      .find('input[name=confirm_urls]')
      .attr('value', confirmUrlsRef.current)
    $(outsourceFormRef.current)
      .find('input[name=data_key]')
      .attr('value', dataKeyRef.current)

    //IMPORTANT post out the quotes
    $(outsourceFormRef.current)
      .find('input[name=quoteData]')
      .attr('value', JSON.stringify(quoteResponseRef.current))
    $(outsourceFormRef.current).submit()
    $(outsourceFormRef.current)
      .find('input[name=quoteData]')
      .attr('value', '')

    const data = {
      event: 'outsource_clicked',
      quote_data: quoteResponseRef.current,
    }
    CommonUtils.dispatchAnalyticsEvents(data)
  }

  const openOutsourcePage = () => {
    window.open(job.get('outsource').get('quote_review_link'), '_blank')
  }

  const clickRevision = () => {
    const checked = revisionCheckboxRef.current.checked
    const service = checked ? 'premium' : 'professional'
    setRevision(checked)
    setTimeout(() => {
      fetchOutsourceQuote(selectedDateRef.current, service)
    })
  }

  const getDeliveryDate = () => {
    return getDeliveryDateFromQuote(chunkQuote, revision)
  }

  const checkChosenDateIsAfter = () => {
    if (outsource && selectedDateRef.current) {
      if (revision && chunkQuote.get('r_delivery')) {
        return (
          selectedDateRef.current >
          new Date(chunkQuote.get('r_delivery')).getTime()
        )
      } else {
        return (
          selectedDateRef.current >
          new Date(chunkQuote.get('delivery')).getTime()
        )
      }
    }
    return false
  }

  const getPrice = () => {
    if (!isNull(job.get('outsource'))) {
      const price = job.get('outsource').get('price')
      return getCurrencyPrice(parseFloat(price))
    } else if (outsource && chunkQuote) {
      let price
      if (revision) {
        price = parseFloat(
          parseFloat(chunkQuote.get('r_price')) +
            parseFloat(chunkQuote.get('price')),
        )
      } else {
        price = parseFloat(chunkQuote.get('price'))
      }
      return getCurrencyPrice(parseFloat(price))
    }
  }

  const getPricePW = (price) => {
    if (outsource) {
      return (parseFloat(price) / standardWC)
        .toFixed(3)
        .replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')
    }
  }

  const getTranslatedWords = () => {
    if (outsource && chunkQuote) {
      return chunkQuote
        .get('t_words_total')
        .toString()
        .replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')
    }
  }

  const getUserEmail = () => {
    const userInfo = UserStore.getUser()
    return userInfo.user ? userInfo.user.email : ''
  }

  const viewMoreClick = () => setExtendedView(true)

  const toggleNeedItFaster = () => setNeedItFaster((prev) => !prev)

  const getNewRates = () => {
    const date = deliveryDate
    const time = selectedTime
    date.setHours(time)
    date.setMinutes((2 - parseFloat(timezone)) * 60)
    const timestamp = new Date(date).getTime()
    const now = new Date().getTime()
    if (timestamp < now) {
      selectedDateRef.current = null
      setErrorPastDate(true)
      setNeedItFaster(false)
    } else {
      selectedDateRef.current = timestamp
      setOutsource(false)
      setErrorPastDate(false)
      setNeedItFaster(false)
      fetchOutsourceQuote(timestamp)
    }
  }

  const getLoaderHtml = () => {
    let msg = 'Choosing the best available translator...'
    if (translatorsNumber && parseInt(translatorsNumber.asInt) > 30) {
      msg =
        'Choosing the best available translator from the matching ' +
        translatorsNumber.printable +
        '...'
    }
    return (
      <div className="translated-loader">
        <img src="../../public/img/loader-matecat-translated-outsource.gif" />
        <div className="text-loader-outsource">{msg}</div>
      </div>
    )
  }

  const getExtendedView = () => {
    const checkboxDisabledClass = outsourceConfirmed ? 'disabled' : ''
    const delivery = getDeliveryDate()
    const showDateMessage = checkChosenDateIsAfter()
    const price = getPrice()
    const priceCurrencySymbol = getPriceCurrencySymbol()
    const translatedWords = getTranslatedWords()
    const email = getUserEmail()
    const pricePWord = getPricePW(price)

    return (
      <div className="outsource-to-vendor sixteen wide column">
        <div className="payment-service">
          <div className="service-box">
            <div className="service project-management">
              Outsource: Project Management{' '}
            </div>
            <div className="service translation"> + Translation </div>
            {revision ? (
              <div className="service revision"> + Revision</div>
            ) : null}
          </div>
          <div className="fiducial-logo">
            <div className="translated-logo">
              Guaranteed by
              <img
                className="logo-t"
                src="/public/img/matecat-logo-translated.svg"
              />
            </div>
          </div>
        </div>
        {outsource ? (
          <div className="payment-details-box">
            <div className="translator-job-details">
              {chunkQuote.get('t_name') !== '' ? (
                <div className="translator-details-box">
                  <div className="ui list left">
                    <div className="item">
                      <b>{chunkQuote.get('t_name')}</b> by Translated
                    </div>
                  </div>
                  <div className="ui list right">
                    <div className="item">
                      <b>{translatedWords}</b> words translated last 12 months
                    </div>
                    <div className="item">
                      <b>
                        {chunkQuote.get('t_experience_years')} years of
                        experience
                      </b>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="translator-details-box">
                  <div className="translator-no-found">
                    <p>
                      Translated uses the <b>most qualified translator</b>{' '}
                      <br /> and{' '}
                      <b>
                        keeps using the same translator for your next
                        projects.{' '}
                      </b>
                    </p>
                  </div>
                </div>
              )}

              <div className="job-details-box">
                <div className="source-target-outsource st-details">
                  <div className="source-box">{job.get('sourceTxt')}</div>
                  <div className="in-to">
                    <i className="icon-chevron-right icon" />
                  </div>
                  <div className="target-box">{job.get('targetTxt')}</div>
                </div>
                <div className="job-payment">
                  <div className="payable">
                    {numberWithCommas(chunkQuote.get('words'))} words
                  </div>
                </div>
              </div>
              {outsourceConfirmed ? (
                ''
              ) : (
                <div className="job-price">
                  {priceCurrencySymbol}{' '}
                  {getCurrencyPrice(chunkQuote.get('price')).replace(
                    /(\d)(?=(\d{3})+(?!\d))/g,
                    '$1,',
                  )}
                </div>
              )}
            </div>
            <div className="revision-box">
              <div className="add-revision">
                <div className={'ui checkbox ' + checkboxDisabledClass}>
                  <input
                    type="checkbox"
                    checked={revision}
                    ref={revisionCheckboxRef}
                    onChange={clickRevision}
                  />
                  <label>Add Revision</label>
                </div>
              </div>
              {outsourceConfirmed ? (
                ''
              ) : (
                <div className="job-price">
                  {priceCurrencySymbol}{' '}
                  {getCurrencyPrice(chunkQuote.get('r_price')).replace(
                    /(\d)(?=(\d{3})+(?!\d))/g,
                    '$1,',
                  )}
                </div>
              )}
            </div>
            {!errorQuote ? (
              !needItFaster ? (
                <div className="delivery-order">
                  <div className="delivery-box">
                    <label>Delivery date:</label>

                    <div className="delivery-date">
                      {delivery.day + ' ' + delivery.month}
                    </div>
                    <div className="atdd">at</div>
                    <div className="delivery-time">{delivery.time}</div>

                    <div className="gmt">
                      <GMTSelect changeValue={changeTimezone} />
                    </div>

                    {!outsourceConfirmed ? (
                      <div className="need-it-faster">
                        {errorPastDate ? (
                          <div className="errors-date past-date">
                            * Chosen delivery date is in the past
                          </div>
                        ) : null}
                        {quoteNotAvailable ? (
                          <div className="errors-date generic-error">
                            * Deadline too close, pick another one.
                          </div>
                        ) : null}

                        {showDateMessage ? (
                          <div className="errors-date too-far-date">
                            We will deliver before the selected date
                            <div
                              className="tip"
                              data-tooltip="This date already provides us with all the time we need to deliver quality work at the lowest price"
                              data-position="bottom center"
                              data-variation="wide"
                            >
                              <HelpCircle />
                            </div>
                          </div>
                        ) : (
                          ''
                        )}
                        <a className="faster" onClick={toggleNeedItFaster}>
                          Need it faster?
                        </a>
                      </div>
                    ) : (
                      ''
                    )}
                  </div>
                  {outsourceConfirmed && !jobOutsourced ? (
                    <div className="confirm-delivery-input">
                      <div className="back" onClick={goBack}>
                        <a className="outsource-goBack">
                          <i className="icon-chevron-left icon" />
                          Back
                        </a>
                      </div>
                      <div className="email-confirm">
                        Insert your email and we'll start working on your
                        project instantly.
                      </div>
                      <div className="ui input">
                        <input
                          type="text"
                          placeholder="Insert email"
                          defaultValue={email}
                        />
                      </div>
                    </div>
                  ) : (
                    ''
                  )}
                  {outsourceConfirmed && jobOutsourced ? (
                    <div className="confirm-delivery-box">
                      <div className="confirm-title">Order sent correctly</div>
                      <p>
                        Thank you for choosing our Outsource service
                        <br />
                        You will soon be contacted by a Account Manager to send
                        you an invoice
                      </p>
                    </div>
                  ) : (
                    ''
                  )}
                </div>
              ) : (
                <div className="delivery-order need-it-faster-box">
                  <a
                    className="need-it-faster-close"
                    onClick={toggleNeedItFaster}
                  >
                    <i className="icon-cancel3 icon need-it-faster-close-icon" />
                  </a>
                  <div className="delivery-box">
                    <div className="ui form">
                      <div className="fields">
                        <div className="field">
                          <label>Delivery Date</label>
                          <div className="ui calendar">
                            <div className="ui input">
                              <DatePicker
                                selected={deliveryDate}
                                onChange={(date) => setDeliveryDate(date)}
                              />
                            </div>
                          </div>
                        </div>
                        <div className="field input-time">
                          <Select
                            label="Time"
                            onSelect={({id}) => setSelectedTime(id)}
                            activeOption={timeOptions.find(
                              ({id}) => id === selectedTime,
                            )}
                            options={timeOptions}
                          />
                        </div>
                        <div className="field gmt">
                          <GMTSelect
                            showLabel={true}
                            changeValue={changeTimezone}
                          />
                        </div>
                        <div className="field">
                          <Button
                            type={BUTTON_TYPE.PRIMARY}
                            mode={BUTTON_MODE.OUTLINE}
                            className="get-price"
                            onClick={getNewRates}
                          >
                            Get Price
                          </Button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )
            ) : (
              <div className="delivery-order-not-available">
                <div className="quote-not-available-message">
                  Quote not available, please contact us at info@translated.net
                  or call +39 06 90 254 001
                </div>
              </div>
            )}

            {!errorQuote ? (
              <div className="order-box-outsource">
                <div className="order-box">
                  <div className="outsource-price">
                    {priceCurrencySymbol}{' '}
                    {price.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')}
                  </div>
                  <DropdownMenu
                    toggleButtonProps={{
                      children: (
                        <>
                          <a className="price-pw">
                            about {priceCurrencySymbol} {pricePWord} / word
                          </a>
                        </>
                      ),
                      mode: BUTTON_MODE.LINK,
                    }}
                    items={Object.keys(currencies).map((key) => ({
                      label: currencies[key].name,
                      onClick: () => onCurrencyChange(key),
                    }))}
                  />
                </div>
                <div className="order-button-outsource">
                  {!outsourceConfirmed ? (
                    <Button
                      type={BUTTON_TYPE.SUCCESS}
                      className="open-order"
                      id="accept-outsource-quote"
                      onClick={sendOutsource}
                    >
                      Order now
                    </Button>
                  ) : !jobOutsourced ? (
                    <Button
                      type={BUTTON_TYPE.SUCCESS}
                      className="open-order"
                      id="accept-outsource-quote"
                      onClick={sendOutsource}
                    >
                      Confirm
                    </Button>
                  ) : (
                    <Button
                      type={BUTTON_TYPE.SUCCESS}
                      className="open-outsourced"
                      id="accept-outsource-quote"
                      onClick={openOutsourcePage}
                    >
                      View status
                    </Button>
                  )}
                </div>
              </div>
            ) : null}
          </div>
        ) : (
          <div className="payment-details-box">{getLoaderHtml()}</div>
        )}
        <div className="easy-pay-box">
          <h4 className="easy-pay">
            Easy payments:{' '}
            <span>Pay a single monthly invoice within 30 days of receipt</span>
          </h4>
        </div>
        <OutsourceInfo />
      </div>
    )
  }

  const getCompactView = () => {
    const delivery = getDeliveryDate()
    const price = getPrice()
    const priceCurrencySymbol = getPriceCurrencySymbol()
    const pricePWord = getPricePW(price)
    const email = getUserEmail()

    return (
      <div className="outsource-to-vendor-reduced sixteen wide column">
        {outsource ? (
          <div className="reduced-boxes">
            <div className="container-reduced">
              <div className="title-reduced">Let us do it for you</div>

              <div className="payment-service">
                <div className="service-box">
                  <div className="service project-management">
                    Outsource: PM{' '}
                  </div>
                  <div className="service translation"> + Translation </div>
                  <div className="service revision"> + Revision</div>
                </div>
                <div className="view-more">
                  <a className="open-view-more" onClick={viewMoreClick}>
                    + view more
                  </a>
                </div>
              </div>
              {!errorQuote ? (
                <div className="delivery-order">
                  <div className="delivery-box">
                    <label>Delivery date:</label>
                    <div>
                      <div className="delivery-date">
                        {delivery.day + ' ' + delivery.month}
                      </div>
                      <div className="atdd">at</div>
                      <div className="delivery-time">{delivery.time}</div>
                      <div className="gmt">
                        <GMTSelect
                          direction="up"
                          changeValue={changeTimezone}
                        />
                      </div>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="delivery-order-not-available">
                  <div className="quote-not-available-message">
                    Quote not available, please contact us at
                    info@translated.net or call +39 06 90 254 001
                  </div>
                </div>
              )}

              {outsourceConfirmed && !jobOutsourced ? (
                <div className="confirm-delivery-input">
                  <div className="back" onClick={goBack}>
                    <a className="outsource-goBack">
                      <i className="icon-chevron-left icon" />
                      Back
                    </a>
                  </div>
                  <div className="email-confirm">
                    Great, an Account Manager will contact you to send you the
                    invoice as a customer to this email
                  </div>
                  <div className="ui input">
                    <input
                      type="text"
                      placeholder="Insert email"
                      defaultValue={email}
                    />
                  </div>
                </div>
              ) : (
                ''
              )}
            </div>
            {!errorQuote ? (
              <div className="order-box-outsource">
                <div className="order-box">
                  <div className="outsource-price">
                    {priceCurrencySymbol}{' '}
                    {price.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')}
                  </div>
                  <DropdownMenu
                    toggleButtonProps={{
                      children: (
                        <>
                          <a className="price-pw">
                            about {priceCurrencySymbol} {pricePWord} / word
                          </a>
                        </>
                      ),
                    }}
                    items={Object.keys(currencies).map((key) => ({
                      label: currencies[key].name,
                      onClick: () => onCurrencyChange(key),
                    }))}
                  />
                </div>
                <div className="order-button-outsource">
                  {!outsourceConfirmed ? (
                    <Button
                      type={BUTTON_TYPE.SUCCESS}
                      className="open-order"
                      onClick={sendOutsource}
                    >
                      Order now
                    </Button>
                  ) : !jobOutsourced ? (
                    <Button
                      type={BUTTON_TYPE.SUCCESS}
                      className="confirm-order "
                      onClick={sendOutsource}
                    >
                      Confirm
                    </Button>
                  ) : (
                    <Button
                      type={BUTTON_TYPE.SUCCESS}
                      className="open-outsourced "
                      href=""
                      onClick={openOutsourcePage}
                    >
                      View status
                    </Button>
                  )}
                </div>
              </div>
            ) : null}
            {jobOutsourced ? (
              <div className="confirm-delivery-box">
                <div className="confirm-title">Order sent correctly</div>
                <p>Thank you for choosing our Outsource service.</p>
              </div>
            ) : (
              ''
            )}
          </div>
        ) : (
          getLoaderHtml()
        )}
      </div>
    )
  }

  const containerClass = !extendedView ? 'compact-background' : ''

  if (errorOutsource) {
    return (
      <div className={'background-outsource-vendor ' + containerClass}>
        <div className="outsource-to-vendor-reduced sixteen wide column">
          <div className="outsource-not-available">
            <div className="outsource-not-available-message">
              Quote not available, please contact us at info@translated.net or
              call +39 06 90 254 001
            </div>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className={'background-outsource-vendor ' + containerClass}>
      {extendedView ? getExtendedView() : getCompactView()}
      <form
        id="continueForm"
        action={config.outsource_service_login}
        method="POST"
        target="_blank"
        ref={outsourceFormRef}
      >
        <input type="hidden" name="url_ok" value="" />
        <input type="hidden" name="url_ko" value="" />
        <input type="hidden" name="confirm_urls" value="" />
        <input type="hidden" name="data_key" value="" />
        <input type="hidden" name="quoteData" value="" />
      </form>
    </div>
  )
}

export default OutsourceVendor
