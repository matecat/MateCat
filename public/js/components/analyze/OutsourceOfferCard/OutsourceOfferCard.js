import React, {useId} from 'react'
import PropTypes from 'prop-types'
import useOutsourceQuote from '../../../hooks/useOutsourceQuote'
import useCurrencyRates from '../../../hooks/useCurrencyRates'
import CommonUtils from '../../../utils/commonUtils'
import {
  formatWithCommas,
  QUOTE_NOT_AVAILABLE_MESSAGE,
} from '../../outsource/outsourceConstants'
import {CUSTOM_BILLING_MESSAGE} from '../LanguageTree'
import {formatCount} from '../utils/buckets'
import {BuyTranslationLabel} from '../shared/BuyTranslationLabel'
import TranslatedIconSmall from '../../../../img/icons/TranslatedIconSmall'
import styles from './OutsourceOfferCard.module.scss'

export const CONTACT_US_URL = 'https://translated.com/contact-us'
export const QUOTE_DEBOUNCE_MS = 400

const OFFER_TITLE = 'Translated by a professional'
const WHOLE_SPLIT_JOB_TITLE = 'Get this job translated for you'
const ANALYSIS_RUNNING_MESSAGE =
  'The price depends on the weighted word count, so it is ready when the analysis is complete.'

const ContactLink = () => (
  <a
    className={styles.link}
    href={CONTACT_US_URL}
    target="_blank"
    rel="noopener noreferrer"
  >
    Contact us
  </a>
)

const BuyButton = ({disabled, label, onClick}) => (
  <button
    type="button"
    className={styles.buy}
    disabled={disabled}
    onClick={onClick}
  >
    {label ?? <BuyTranslationLabel />}
  </button>
)

BuyButton.propTypes = {
  disabled: PropTypes.bool,
  label: PropTypes.string,
  onClick: PropTypes.func,
}

// The card's frame: eyebrow, title, text, then whatever the state adds
const Offer = ({Heading, titleId, title, text, children}) => (
  <>
    <Heading id={titleId} className={styles.title}>
      {title}
    </Heading>
    {text && <p className={styles.text}>{text}</p>}
    {children}
  </>
)

Offer.propTypes = {
  Heading: PropTypes.string.isRequired,
  titleId: PropTypes.string.isRequired,
  title: PropTypes.string.isRequired,
  text: PropTypes.node,
  children: PropTypes.node,
}

// Mounted only for a quotable job or chunk, so nothing is fetched otherwise
const QuoteOffer = ({
  projectId,
  projectPassword,
  job,
  chunk,
  jobOutsource,
  onBuy,
  frame,
}) => {
  const {getCurrentCurrency, getCurrencyPrice, getPriceCurrencySymbol} =
    useCurrencyRates()
  const {status, chunkQuote, jobOutsourced, retry} = useOutsourceQuote({
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
    return frame({
      title: OFFER_TITLE,
      text: (
        <>
          {QUOTE_NOT_AVAILABLE_MESSAGE} <ContactLink />
        </>
      ),
    })
  }

  if (status === 'error') {
    return frame({
      title: OFFER_TITLE,
      text: 'We could not get a quote.',
      children: (
        <button type="button" className={styles.retry} onClick={retry}>
          Retry
        </button>
      ),
    })
  }

  if (status !== 'ready' || !chunkQuote) {
    return frame({
      title: 'Getting a quote…',
      children: (
        <>
          <span className={styles.loading} role="status">
            <span className={styles.shimmer} />
            <span className={styles.shimmerShort} />
          </span>
          <BuyButton disabled />
        </>
      ),
    })
  }

  const basePrice = jobOutsource
    ? parseFloat(jobOutsource.price)
    : parseFloat(chunkQuote.get('price'))
  const price = formatWithCommas(getCurrencyPrice(basePrice))
  const delivery = CommonUtils.getGMTDate(
    jobOutsource ? jobOutsource.delivery_date : chunkQuote.get('delivery'),
  )
  const details = [
    `${formatCount(chunk.totals.equivalent)} words`,
    delivery &&
      `delivered by ${delivery.day} ${delivery.month} at ${delivery.time}`,
    parseFloat(chunkQuote.get('r_price')) > 0 && 'revision available',
  ]
    .filter(Boolean)
    .join(' · ')

  return frame({
    title: `${OFFER_TITLE} for ${getPriceCurrencySymbol(chunkQuote)} ${price}`,
    text: details,
    children: (
      <BuyButton
        label={jobOutsourced ? 'View order' : undefined}
        onClick={handleBuy}
      />
    ),
  })
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

QuoteOffer.propTypes = {...offerPropTypes, frame: PropTypes.func.isRequired}

/**
 * Price and delivery date of the selected job or chunk, from a quote asked
 * once the analysis is complete; "Buy translation" opens the order drawer.
 * A whole split job is not quoted: its chunks are bought one by one. Hidden
 * when outsourcing is off.
 */
export const OutsourceOfferCard = ({
  headingLevel = 3,
  projectId,
  projectPassword,
  job,
  chunk,
  jobOutsource,
  actionsEnabled,
  onBuy,
}) => {
  const titleId = useId()
  if (!config.enable_outsource) return null

  const Heading = `h${headingLevel}`
  const frame = ({title, text, children}) => (
    <Offer Heading={Heading} titleId={titleId} title={title} text={text}>
      {children}
    </Offer>
  )

  let content
  if (!chunk) {
    content = frame({
      title: WHOLE_SPLIT_JOB_TITLE,
      text: `Each of the ${job.chunkCount} chunks is bought on its own. To buy the whole job in one order, merge it first.`,
      children: (
        <BuyButton
          label="Choose a chunk"
          disabled={!actionsEnabled}
          onClick={() => onBuy(job, null)}
        />
      ),
    })
  } else if (!actionsEnabled) {
    content = frame({
      title: OFFER_TITLE,
      text: ANALYSIS_RUNNING_MESSAGE,
      children: <BuyButton disabled />,
    })
  } else if (!job.outsourceAvailable) {
    content = frame({
      title: OFFER_TITLE,
      text: (
        <>
          {job.outsourceInfo?.custom_payable_rate
            ? `${CUSTOM_BILLING_MESSAGE}.`
            : QUOTE_NOT_AVAILABLE_MESSAGE}{' '}
          <ContactLink />
        </>
      ),
    })
  } else {
    content = (
      <QuoteOffer
        projectId={projectId}
        projectPassword={projectPassword}
        job={job}
        chunk={chunk}
        jobOutsource={jobOutsource}
        actionsEnabled={actionsEnabled}
        onBuy={onBuy}
        frame={frame}
      />
    )
  }

  return (
    <section className={styles.card} aria-labelledby={titleId}>
      <span className={styles.eyebrow}>
        Professional translation by
        <span className={styles.logo} aria-hidden="true">
          <TranslatedIconSmall size={16} />
        </span>
        <span className={styles.hidden}>{' Translated'}</span>
      </span>
      {content}
    </section>
  )
}

OutsourceOfferCard.propTypes = {
  ...offerPropTypes,
  // The card's heading: 3 inside a panel, 2 when it sits on the page itself
  headingLevel: PropTypes.oneOf([2, 3, 4]),
}
