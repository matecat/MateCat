import {useCallback, useMemo, useRef, useState} from 'react'
import Cookies from 'js-cookie'
import useOutsourceQuote, {evictOutsourceQuoteCache} from './useOutsourceQuote'
import useCurrencyRates from './useCurrencyRates'
import CommonUtils from '../utils/commonUtils'
import {formatWithCommas} from '../components/outsource/outsourceConstants'

/**
 * Everything the outsource order form does, without its markup: the quote
 * (revision, delivery date, "need it faster", timezone, currency), the price
 * and the order submission through the hidden vendor form (`outsourceFormRef`,
 * rendered with OutsourceOrderForm). Used by OutsourceVendor and by the
 * analysis page's order drawer.
 *
 * @param {object} params
 * @param {object} params.job Immutable job (id, password, source, target, outsource)
 * @param {object} params.project Immutable project (id, password)
 * @param {number|string} [params.standardWC] weighted words, for the price per word
 * @param {boolean} [params.outsourceEnabled]
 * @param {boolean} [params.cache] share quotes with the page's other quote users
 */
const useOutsourceOrder = ({
  job,
  project,
  standardWC,
  outsourceEnabled = config.enable_outsource,
  cache = false,
}) => {
  const outsourceFormRef = useRef(null)
  const timezoneRef = useRef(Cookies.get('matecat_timezone'))

  const [needItFaster, setNeedItFaster] = useState(false)
  const [errorPastDate, setErrorPastDate] = useState(false)

  // The hook takes plain values: convert the Immutable job outsource record here
  const immutableJobOutsource = job.get('outsource')
  const jobOutsource = useMemo(
    () =>
      immutableJobOutsource && immutableJobOutsource.toJS
        ? immutableJobOutsource.toJS()
        : (immutableJobOutsource ?? null),
    [immutableJobOutsource],
  )

  // --- Hooks ---
  const {
    getCurrentCurrency,
    getCurrencyPrice,
    getPriceCurrencySymbol,
    onCurrencyChange,
  } = useCurrencyRates()

  const {
    status,
    retry,
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
  } = useOutsourceQuote({
    projectId: project.get('id'),
    projectPassword: project.get('password'),
    jobId: job.get('id'),
    jobPassword: job.get('password'),
    jobOutsource,
    enabled: !!outsourceEnabled,
    getCurrentCurrency,
    cache,
  })

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
    // The job is being ordered: no cached quote may offer it again
    evictOutsourceQuoteCache(job.get('id'), job.get('password'))

    CommonUtils.dispatchAnalyticsEvents({
      event: 'outsource_clicked',
      quote_data: quoteResponseRef.current,
    })
  }, [
    job,
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

  return {
    // Quote state
    outsource,
    revision,
    chunkQuote,
    status,
    retry,
    outsourceConfirmed,
    jobOutsourced,
    quoteNotAvailable,
    errorQuote,
    errorOutsource,
    deliveryDate,
    selectedTime,
    needItFaster,
    errorPastDate,
    // Derived
    price,
    priceCurrencySymbol,
    pricePWord,
    translatedWords,
    delivery,
    showDateMessage,
    getCurrencyPrice,
    // Actions
    toggleRevision,
    changeTimezone,
    handleCurrencyChange,
    toggleNeedItFaster,
    getNewRates,
    setDeliveryDate,
    setSelectedTime,
    sendOutsource,
    openOutsourcePage,
    outsourceFormRef,
  }
}

export default useOutsourceOrder
