import React, {useCallback, useRef, useState} from 'react'
import Cookies from 'js-cookie'
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
import {Badge, BADGE_TYPE} from '../common/Badge'
import {formatWithCommas} from './outsourceConstants'

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
  const [needItFaster, setNeedItFaster] = useState(false)
  const [errorPastDate, setErrorPastDate] = useState(false)

  const outsourceFormRef = useRef(null)
  const timezoneRef = useRef(Cookies.get('matecat_timezone'))

  // --- Hooks ---
  const {
    getCurrentCurrency,
    getCurrencyPrice,
    getPriceCurrencySymbol,
    onCurrencyChange,
  } = useCurrencyRates()

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
    updateTimezoneRef,
    getDeliveryDateFromQuote,
    checkChosenDateIsAfter,
  } = useOutsourceQuote({job, project, getCurrentCurrency})

  // --- Derived values ---
  const priceCurrencySymbol =
    outsource && chunkQuote ? getPriceCurrencySymbol(chunkQuote) : ''

  const price = (() => {
    if (job.get('outsource') != null) {
      return getCurrencyPrice(parseFloat(job.get('outsource').get('price')))
    }
    if (outsource && chunkQuote) {
      const base = parseFloat(chunkQuote.get('price'))
      const total = revision
        ? base + parseFloat(chunkQuote.get('r_price'))
        : base
      return getCurrencyPrice(total)
    }
  })()

  const pricePWord =
    outsource && price
      ? formatWithCommas((parseFloat(price) / standardWC).toFixed(3))
      : undefined

  const translatedWords =
    outsource && chunkQuote
      ? formatWithCommas(chunkQuote.get('t_words_total'))
      : undefined

  const delivery = getDeliveryDateFromQuote(revision)
  const showDateMessage = checkChosenDateIsAfter()

  const email = (() => {
    const userInfo = UserStore.getUser()
    return userInfo.user ? userInfo.user.email : ''
  })()

  // --- Handlers ---
  const changeTimezone = useCallback(
    (value) => {
      Cookies.set('matecat_timezone', value, {secure: true})
      timezoneRef.current = value
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
    const date = new Date(deliveryDate)
    date.setHours(selectedTime)
    date.setMinutes((2 - parseFloat(timezoneRef.current)) * 60)
    const timestamp = date.getTime()
    if (timestamp < Date.now()) {
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
  }, [deliveryDate, selectedTime, selectedDateRef, setOutsource, fetchQuote])

  const sendOutsource = useCallback(() => {
    quoteResponseRef.current[0] = chunkQuote.toJS()
    const form = outsourceFormRef.current
    form.querySelector('input[name=url_ok]').value = urlOkRef.current
    form.querySelector('input[name=url_ko]').value = urlKoRef.current
    form.querySelector('input[name=confirm_urls]').value =
      confirmUrlsRef.current
    form.querySelector('input[name=data_key]').value = dataKeyRef.current
    form.querySelector('input[name=quoteData]').value = JSON.stringify(
      quoteResponseRef.current,
    )
    form.submit()
    form.querySelector('input[name=quoteData]').value = ''

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
          errorQuote={errorQuote}
          job={job}
          translatedWords={translatedWords}
          priceCurrencySymbol={priceCurrencySymbol}
          getCurrencyPrice={getCurrencyPrice}
          onToggleRevision={toggleRevision}
          translatorsNumber={translatorsNumber}
          deliveryProps={deliveryProps}
          orderBoxProps={orderBoxProps}
        />
      ) : (
        <CompactView
          outsource={outsource}
          errorQuote={errorQuote}
          onViewMore={() => setExtendedView(true)}
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
  errorQuote,
  job,
  translatedWords,
  priceCurrencySymbol,
  getCurrencyPrice,
  onToggleRevision,
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
