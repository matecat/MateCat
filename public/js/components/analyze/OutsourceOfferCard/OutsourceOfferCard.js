import React, {useId} from 'react'
import PropTypes from 'prop-types'
import useOutsourceQuote from '../../../hooks/useOutsourceQuote'
import useCurrencyRates from '../../../hooks/useCurrencyRates'
import CommonUtils from '../../../utils/commonUtils'
import {QUOTE_NOT_AVAILABLE_MESSAGE} from '../../outsource/OutsourceVendor'
import {formatWithCommas} from '../../outsource/outsourceConstants'
import {Button, BUTTON_SIZE, BUTTON_TYPE} from '../../common/Button/Button'
import {Pending} from '../shared/Pending'
import {CUSTOM_BILLING_MESSAGE} from '../LanguageTree'
import styles from './OutsourceOfferCard.module.scss'

export const CONTACT_US_URL = 'https://translated.com/contact-us'
export const QUOTE_DEBOUNCE_MS = 400

const WHOLE_SPLIT_JOB_MESSAGE =
  'Chunks are bought one at a time. Merge the job to buy it in one order.'
const ANALYSIS_RUNNING_MESSAGE =
  'The price depends on the weighted word count, so it is ready when the analysis is complete.'

const ContactLink = () => (
  <a href={CONTACT_US_URL} target="_blank" rel="noopener noreferrer">
    Contact us
  </a>
)

const BuyButton = ({disabled, label = 'Buy translation', onClick}) => (
  <Button
    type={BUTTON_TYPE.PRIMARY}
    size={BUTTON_SIZE.MEDIUM}
    tabIndex="0"
    disabled={disabled}
    onClick={onClick}
  >
    {label}
  </Button>
)

BuyButton.propTypes = {
  disabled: PropTypes.bool,
  label: PropTypes.string,
  onClick: PropTypes.func,
}

// Mounted only for a quotable job or chunk, so nothing is fetched otherwise
const QuoteOffer = ({
  projectId,
  projectPassword,
  job,
  chunk,
  jobOutsource,
  onBuy,
}) => {
  const {getCurrentCurrency, getCurrencyPrice, getPriceCurrencySymbol} =
    useCurrencyRates()
  const {status, chunkQuote, revision, jobOutsourced, retry} =
    useOutsourceQuote({
      projectId,
      projectPassword,
      jobId: job.id,
      jobPassword: chunk.password,
      jobOutsource,
      enabled: true,
      getCurrentCurrency,
      debounceMs: QUOTE_DEBOUNCE_MS,
      cache: true,
    })

  // The page fires the outsource request event when the order form opens
  const handleBuy = () => onBuy(job, chunk)

  if (status === 'unavailable') {
    return (
      <p className={styles.message}>
        {QUOTE_NOT_AVAILABLE_MESSAGE} <ContactLink />
      </p>
    )
  }
  if (status === 'error') {
    return (
      <>
        <p className={styles.message}>We could not get a quote.</p>
        <Button size={BUTTON_SIZE.MEDIUM} tabIndex="0" onClick={retry}>
          Retry
        </Button>
      </>
    )
  }

  if (status !== 'ready' || !chunkQuote) {
    return (
      <>
        <p className={styles.message} role="status">
          Getting a quote…
        </p>
        <div className={styles.quote}>
          <Pending known={false} />
          <Pending known={false} />
        </div>
        <BuyButton disabled />
      </>
    )
  }

  const basePrice = jobOutsource
    ? parseFloat(jobOutsource.price)
    : parseFloat(chunkQuote.get('price')) +
      (revision ? parseFloat(chunkQuote.get('r_price')) : 0)
  const price = formatWithCommas(getCurrencyPrice(basePrice))
  const delivery = CommonUtils.getGMTDate(
    jobOutsource
      ? jobOutsource.delivery_date
      : revision && chunkQuote.get('r_delivery')
        ? chunkQuote.get('r_delivery')
        : chunkQuote.get('delivery'),
  )

  return (
    <>
      <div className={styles.quote}>
        <span className={styles.price}>
          {`${getPriceCurrencySymbol(chunkQuote)}${price}`}
        </span>
        {delivery && (
          <span className={styles.delivery}>
            {`Delivery by ${delivery.day} ${delivery.month} at ${delivery.time}`}
          </span>
        )}
      </div>
      <BuyButton
        label={jobOutsourced ? 'View order' : 'Buy translation'}
        onClick={handleBuy}
      />
    </>
  )
}

const offerPropTypes = {
  projectId: PropTypes.oneOfType([PropTypes.number, PropTypes.string])
    .isRequired,
  projectPassword: PropTypes.string.isRequired,
  job: PropTypes.object.isRequired,
  // null for a whole split job
  chunk: PropTypes.object,
  jobOutsource: PropTypes.object,
  actionsEnabled: PropTypes.bool,
  onBuy: PropTypes.func.isRequired,
}

QuoteOffer.propTypes = offerPropTypes

const OfferBody = (props) => {
  const {job, chunk, actionsEnabled} = props

  if (!chunk) {
    return <p className={styles.message}>{WHOLE_SPLIT_JOB_MESSAGE}</p>
  }

  if (!actionsEnabled) {
    return (
      <>
        <p className={styles.message}>{ANALYSIS_RUNNING_MESSAGE}</p>
        <BuyButton disabled />
      </>
    )
  }

  if (!job.outsourceAvailable) {
    return (
      <p className={styles.message}>
        {job.outsourceInfo?.custom_payable_rate
          ? `${CUSTOM_BILLING_MESSAGE}.`
          : QUOTE_NOT_AVAILABLE_MESSAGE}{' '}
        <ContactLink />
      </p>
    )
  }

  return <QuoteOffer {...props} />
}

OfferBody.propTypes = offerPropTypes

/**
 * Price and delivery date of the selected job or chunk, from a quote asked
 * once the analysis is complete. A whole split job is not quoted: its chunks
 * are bought one by one. Hidden when outsourcing is off.
 */
export const OutsourceOfferCard = ({headingLevel = 3, ...offerProps}) => {
  const titleId = useId()
  if (!config.enable_outsource) return null
  const Heading = `h${headingLevel}`

  return (
    <section className={styles.card} aria-labelledby={titleId}>
      <Heading id={titleId} className={styles.title}>
        Professional translation
      </Heading>
      <OfferBody {...offerProps} />
    </section>
  )
}

OutsourceOfferCard.propTypes = {
  ...offerPropTypes,
  // The card's heading: 3 inside a panel, 2 when it sits on the page itself
  headingLevel: PropTypes.oneOf([2, 3, 4]),
}
