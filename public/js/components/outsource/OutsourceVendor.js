import React, {useCallback, useRef, useState} from 'react'
import {isNull} from 'lodash/lang'
import Cookies from 'js-cookie'
import $ from 'jquery'

import useOutsourceQuote from '../../hooks/useOutsourceQuote'
import useCurrencyRates from '../../hooks/useCurrencyRates'

import OutsourceInfo from './OutsourceInfo'
import OutsourceLoader from './components/OutsourceLoader'
import ServiceBox from './components/ServiceBox'
import TranslatorDetails from './components/TranslatorDetails'
import RevisionCheckbox from './components/RevisionCheckbox'
import DeliverySection from './components/DeliverySection'
import OrderBox from './components/OrderBox'
import CommonUtils from '../../utils/commonUtils'
import UserStore from '../../stores/UserStore'
import {Badge, BADGE_MODE, BADGE_TYPE} from '../common/Badge'

const QUOTE_NOT_AVAILABLE_MESSAGE =
  'Quote not available, please contact us at info@translated.net or call +39 06 90 254 001'

const OutsourceVendor = ({
  job,
  project,
  extendedView: extendedViewProp,
  standardWC,
  translatorsNumber,
}) => {
  const [extendedView, setExtendedView] = useState(extendedViewProp)
  const [timezone, setTimezone] = useState(Cookies.get('matecat_timezone'))
  const [needItFaster, setNeedItFaster] = useState(false)
  const [errorPastDate, setErrorPastDate] = useState(false)

  const outsourceFormRef = useRef(null)

  // --- Hooks ---
  const {
    getCurrentCurrency,
    getCurrencyPrice,
    getPriceCurrencySymbol,
    onCurrencyChange,
  } = useCurrencyRates()

  const quote = useOutsourceQuote({job, project, getCurrentCurrency})

  const {
    outsource,
    setOutsource,
    revision,
    chunkQuote,
    setChunkQuote,
    outsourceConfirmed,
    jobOutsourced,
    quoteNotAvailable,
    errorQuote,
    errorOutsource,
    deliveryDate,
    setDeliveryDate,
    selectedTime,
    setSelectedTime,
    quoteResponseRef,
    urlOkRef,
    urlKoRef,
    confirmUrlsRef,
    dataKeyRef,
    selectedDateRef,
    fetchQuote,
    toggleRevision,
    goBack,
    updateTimezoneRef,
    getDeliveryDateFromQuote,
    checkChosenDateIsAfter,
  } = quote

  // --- Derived values ---
  const priceCurrencySymbol =
    outsource && chunkQuote ? getPriceCurrencySymbol(chunkQuote) : ''

  const getPrice = useCallback(() => {
    if (!isNull(job.get('outsource'))) {
      const price = job.get('outsource').get('price')
      return getCurrencyPrice(parseFloat(price))
    } else if (outsource && chunkQuote) {
      const price = revision
        ? parseFloat(chunkQuote.get('r_price')) +
          parseFloat(chunkQuote.get('price'))
        : parseFloat(chunkQuote.get('price'))
      return getCurrencyPrice(parseFloat(price))
    }
  }, [job, outsource, chunkQuote, revision, getCurrencyPrice])

  const getPricePW = useCallback(
    (price) => {
      if (outsource && price) {
        return (parseFloat(price) / standardWC)
          .toFixed(3)
          .replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')
      }
    },
    [outsource, standardWC],
  )

  const getTranslatedWords = useCallback(() => {
    if (outsource && chunkQuote) {
      return chunkQuote
        .get('t_words_total')
        .toString()
        .replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1,')
    }
  }, [outsource, chunkQuote])

  const getUserEmail = () => {
    const userInfo = UserStore.getUser()
    return userInfo.user ? userInfo.user.email : ''
  }

  // --- Handlers ---
  const changeTimezone = useCallback(
    (value) => {
      Cookies.set('matecat_timezone', value, {secure: true})
      setTimezone(value)
      updateTimezoneRef(value)
    },
    [updateTimezoneRef],
  )

  const handleCurrencyChange = useCallback(
    (key) => onCurrencyChange(key, chunkQuote, setChunkQuote),
    [onCurrencyChange, chunkQuote, setChunkQuote],
  )

  const toggleNeedItFaster = useCallback(
    () => setNeedItFaster((prev) => !prev),
    [],
  )

  const getNewRates = useCallback(() => {
    const date = deliveryDate
    date.setHours(selectedTime)
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
      fetchQuote(timestamp)
    }
  }, [
    deliveryDate,
    selectedTime,
    timezone,
    selectedDateRef,
    setOutsource,
    fetchQuote,
  ])

  const sendOutsource = useCallback(() => {
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

    $(outsourceFormRef.current)
      .find('input[name=quoteData]')
      .attr('value', JSON.stringify(quoteResponseRef.current))
    $(outsourceFormRef.current).submit()
    $(outsourceFormRef.current).find('input[name=quoteData]').attr('value', '')

    CommonUtils.dispatchAnalyticsEvents({
      event: 'outsource_clicked',
      quote_data: quoteResponseRef.current,
    })
  }, [
    chunkQuote,
    quoteResponseRef,
    urlOkRef,
    urlKoRef,
    confirmUrlsRef,
    dataKeyRef,
  ])

  const openOutsourcePage = useCallback(() => {
    window.open(job.get('outsource').get('quote_review_link'), '_blank')
  }, [job])

  // --- Shared props for sub-components ---
  const price = getPrice()
  const pricePWord = getPricePW(price)
  const delivery = getDeliveryDateFromQuote(revision)
  const showDateMessage = checkChosenDateIsAfter()
  const email = getUserEmail()
  const translatedWords = getTranslatedWords()

  const orderBoxProps = {
    price,
    priceCurrencySymbol,
    pricePWord,
    outsourceConfirmed,
    jobOutsourced,
    onSendOutsource: sendOutsource,
    onOpenOutsourcePage: openOutsourcePage,
    onCurrencyChange: handleCurrencyChange,
  }

  const deliveryProps = {
    delivery,
    errorQuote,
    needItFaster,
    outsourceConfirmed,
    errorPastDate,
    quoteNotAvailable,
    showDateMessage,
    deliveryDate,
    selectedTime,
    onChangeTimezone: changeTimezone,
    onToggleNeedItFaster: toggleNeedItFaster,
    onDateChange: setDeliveryDate,
    onTimeChange: setSelectedTime,
    onGetNewRates: getNewRates,
    extendedView,
  }

  if (errorOutsource) {
    return (
      <div className="outsource-to-vendor-reduced ">
        <div className="outsource-not-available">
          <div className="outsource-not-available-message">
            {QUOTE_NOT_AVAILABLE_MESSAGE}
          </div>
        </div>
      </div>
    )
  }

  return (
    <>
      {extendedView ? (
        <ExtendedView
          outsource={outsource}
          revision={revision}
          chunkQuote={chunkQuote}
          outsourceConfirmed={outsourceConfirmed}
          jobOutsourced={jobOutsourced}
          errorQuote={errorQuote}
          job={job}
          translatedWords={translatedWords}
          priceCurrencySymbol={priceCurrencySymbol}
          getCurrencyPrice={getCurrencyPrice}
          onToggleRevision={toggleRevision}
          email={email}
          onGoBack={goBack}
          translatorsNumber={translatorsNumber}
          deliveryProps={deliveryProps}
          orderBoxProps={orderBoxProps}
        />
      ) : (
        <CompactView
          outsource={outsource}
          errorQuote={errorQuote}
          outsourceConfirmed={outsourceConfirmed}
          jobOutsourced={jobOutsourced}
          delivery={delivery}
          email={email}
          onViewMore={() => setExtendedView(true)}
          onGoBack={goBack}
          onChangeTimezone={changeTimezone}
          translatorsNumber={translatorsNumber}
          orderBoxProps={orderBoxProps}
          deliveryProps={deliveryProps}
        />
      )}

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
    </>
  )
}

// --- Extended View ---
const ExtendedView = ({
  outsource,
  revision,
  chunkQuote,
  outsourceConfirmed,
  jobOutsourced,
  errorQuote,
  job,
  translatedWords,
  priceCurrencySymbol,
  getCurrencyPrice,
  onToggleRevision,
  email,
  onGoBack,
  translatorsNumber,
  deliveryProps,
  orderBoxProps,
}) => (
  <>
    <ServiceBox revision={revision} />

    {outsource ? (
      <div className="payment-details-box">
        <TranslatorDetails
          chunkQuote={chunkQuote}
          translatedWords={translatedWords}
          job={job}
          outsourceConfirmed={outsourceConfirmed}
          priceCurrencySymbol={priceCurrencySymbol}
          getCurrencyPrice={getCurrencyPrice}
        />

        <RevisionCheckbox
          revision={revision}
          outsourceConfirmed={outsourceConfirmed}
          onToggle={onToggleRevision}
          priceCurrencySymbol={priceCurrencySymbol}
          getCurrencyPrice={getCurrencyPrice}
          revisionPrice={chunkQuote.get('r_price')}
        />
        <div className="delivery-order">
          <DeliverySection {...deliveryProps} />
          {!errorQuote && <OrderBox {...orderBoxProps} />}
        </div>
      </div>
    ) : (
      <div className="payment-details-box">
        <OutsourceLoader translatorsNumber={translatorsNumber} />
      </div>
    )}
    <OutsourceInfo />
  </>
)

// --- Compact View ---
const CompactView = ({
  outsource,
  errorQuote,
  onViewMore,
  deliveryProps,
  translatorsNumber,
  orderBoxProps,
}) => (
  <>
    {outsource ? (
      <div className="payment-details-box">
        <div className="delivery-order">
          <div>
            <div className={'compact-view-header'}>Let us do it for you</div>
            <div className={'order-badge-container'}>
              Outsource: <Badge>PM</Badge>+
              <Badge type={BADGE_TYPE.PRIMARY}>Translation</Badge>+
              <Badge type={BADGE_TYPE.GREEN}>Revision</Badge>
            </div>
            <a onClick={onViewMore}>+ View More</a>
          </div>
          <DeliverySection {...deliveryProps} />
          {!errorQuote && <OrderBox {...orderBoxProps} />}
        </div>
      </div>
    ) : (
      <div className="payment-details-box">
        <OutsourceLoader translatorsNumber={translatorsNumber} />
      </div>
    )}
  </>
)

export default OutsourceVendor
