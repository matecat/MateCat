import React, {useState} from 'react'
import Cookies from 'js-cookie'
import PropTypes from 'prop-types'
import useOutsourceOrder from '../../../hooks/useOutsourceOrder'
import CommonUtils from '../../../utils/commonUtils'
import {
  DROPDOWN_MENU_TRIGGER_MODE,
  DropdownMenu,
} from '../../common/DropdownMenu/DropdownMenu'
import {BUTTON_MODE, BUTTON_SIZE} from '../../common/Button/Button'
import {GMTSelect} from '../GMTSelect'
import DatePicker from 'react-datepicker'
import {Select} from '../../common/Select'
import OutsourceOrderForm from '../components/OutsourceOrderForm'
import ChevronDown from '../../../../img/icons/ChevronDown'
import {
  currencies,
  formatWithCommas,
  QUOTE_NOT_AVAILABLE_MESSAGE,
  timeOptions,
} from '../outsourceConstants'
import styles from './OutsourceOrderPanel.module.scss'

import 'react-datepicker/dist/react-datepicker.css'

export const CHAT_EVENT = 'openChat'

// The quote and the delivery date read the timezone cookie: set it first
export const checkTimezone = () => {
  let timezoneToShow = Cookies.get('matecat_timezone')
  if (!timezoneToShow) {
    timezoneToShow = -1 * (new Date().getTimezoneOffset() / 60)
    Cookies.set('matecat_timezone', timezoneToShow, {secure: true})
  }
}

const pluralize = (count, one, many) => `${count} ${count === 1 ? one : many}`

const Translator = ({chunkQuote, translatedWords}) => {
  const name = chunkQuote.get('t_name')
  if (!name) {
    return (
      <p className={styles.translatorNone}>
        Translated uses the most qualified translator and keeps using the same
        translator for your next projects.
      </p>
    )
  }
  const years = Number(chunkQuote.get('t_experience_years')) || 0
  return (
    <div className={styles.translator}>
      <span className={styles.avatar} aria-hidden="true">
        {name.charAt(0)}
      </span>
      <div className={styles.translatorText}>
        <span className={styles.muted}>
          Best identified translator for this job
        </span>
        <span>
          <strong>{name}</strong> by Translated
        </span>
        <span className={styles.mutedSmall}>
          {`${translatedWords} words translated last 12 months · ${pluralize(years, 'year', 'years')} of experience`}
        </span>
      </div>
    </div>
  )
}

Translator.propTypes = {
  chunkQuote: PropTypes.object.isRequired,
  translatedWords: PropTypes.string,
}

/**
 * The outsource order form (Manage and the analysis page drawer): quote,
 * translator, revision, delivery date with "Need it faster?" and timezone,
 * total with currency, and the order submission (useOutsourceOrder).
 */
export const OutsourceOrderPanel = ({
  job,
  project,
  standardWC,
  serviceLoginUrl = config.outsource_service_login,
  cache = false,
  outsourceEnabled = config.enable_outsource,
}) => {
  // Before the quote hook reads the timezone cookie
  useState(checkTimezone)
  // The currency menu opens in the panel, so it stays above the drawer
  const [panelNode, setPanelNode] = useState(null)

  const {
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
    price,
    priceCurrencySymbol,
    pricePWord,
    translatedWords,
    delivery,
    showDateMessage,
    getCurrencyPrice,
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
  } = useOutsourceOrder({job, project, standardWC, cache, outsourceEnabled})

  const form = (
    <OutsourceOrderForm
      formRef={outsourceFormRef}
      serviceLoginUrl={serviceLoginUrl}
    />
  )

  const failed =
    status === 'error' || status === 'unavailable' || errorOutsource

  // No quote yet: either the first one failed, or it is on its way
  if (!chunkQuote) {
    return (
      <div className={styles.panel} ref={setPanelNode}>
        {failed ? (
          <>
            <p className={styles.message}>
              {status === 'error'
                ? 'We could not get a quote.'
                : QUOTE_NOT_AVAILABLE_MESSAGE}
            </p>
            {status === 'error' && (
              <button
                type="button"
                className={styles.secondary}
                onClick={retry}
              >
                Retry
              </button>
            )}
          </>
        ) : (
          <div className={styles.loading} role="status">
            <span>Getting a quote…</span>
            <span className={styles.shimmer} />
            <span className={styles.shimmerShort} />
          </div>
        )}
        {form}
      </div>
    )
  }

  const pair = `${job.get('source')} › ${job.get('target')}`
  const words = formatWithCommas(chunkQuote.get('words') ?? standardWC ?? '')

  return (
    <div className={styles.panel} ref={setPanelNode}>
      <Translator chunkQuote={chunkQuote} translatedWords={translatedWords} />

      <div className={styles.lines}>
        <div className={styles.line}>
          <span>{`${pair} · ${words} words`}</span>
          {!outsourceConfirmed && (
            <span className={styles.amount}>
              {`${priceCurrencySymbol} ${formatWithCommas(getCurrencyPrice(chunkQuote.get('price')))}`}
            </span>
          )}
        </div>
        <label className={styles.line}>
          <span className={styles.check}>
            <input
              type="checkbox"
              checked={revision}
              disabled={outsourceConfirmed}
              onChange={toggleRevision}
            />
            <span>Add revision</span>
          </span>
          {!outsourceConfirmed && (
            <span className={styles.amount}>
              {`+ ${priceCurrencySymbol} ${formatWithCommas(getCurrencyPrice(chunkQuote.get('r_price')))}`}
            </span>
          )}
        </label>
      </div>

      {needItFaster ? (
        <div className={styles.faster}>
          <span className={styles.muted}>
            Need it faster? Pick a delivery date
          </span>
          <div className={styles.fasterFields}>
            <label className={styles.field}>
              <span className={styles.mutedSmall}>Delivery date</span>
              <DatePicker
                selected={deliveryDate}
                onChange={setDeliveryDate}
                // Fixed: the drawer scrolls, and would clip the calendar
                popperProps={{strategy: 'fixed'}}
              />
            </label>
            <div className={styles.field}>
              <Select
                label="Time"
                onSelect={({id}) => setSelectedTime(id)}
                activeOption={timeOptions.find(({id}) => id === selectedTime)}
                options={timeOptions}
              />
            </div>
            <div className={styles.field}>
              <GMTSelect showLabel={true} changeValue={changeTimezone} />
            </div>
          </div>
          <div className={styles.fasterActions}>
            <button
              type="button"
              className={styles.secondary}
              onClick={getNewRates}
            >
              Get price
            </button>
            <button
              type="button"
              className={styles.link}
              onClick={toggleNeedItFaster}
            >
              Close
            </button>
          </div>
        </div>
      ) : (
        <div className={styles.delivery}>
          <span className={styles.muted}>Delivery date</span>
          {status === 'loading' ? (
            <span className={styles.mutedSmall} role="status">
              Getting a new quote…
            </span>
          ) : errorQuote ? (
            <span className={styles.error}>
              We could not get a new quote.{' '}
              <button
                type="button"
                className={styles.inlineLink}
                onClick={retry}
              >
                Retry
              </button>
            </span>
          ) : (
            delivery && (
              <span className={styles.deliveryDate}>
                {`${delivery.day} ${delivery.month} at ${delivery.time}`}
              </span>
            )
          )}
          <div className={styles.deliveryOptions}>
            <div className={styles.timezone}>
              <GMTSelect changeValue={changeTimezone} />
            </div>
            {!outsourceConfirmed && (
              <button
                type="button"
                className={styles.link}
                onClick={toggleNeedItFaster}
              >
                Need it faster?
              </button>
            )}
          </div>
          {errorPastDate && (
            <span className={styles.error}>
              The chosen delivery date is in the past.
            </span>
          )}
          {quoteNotAvailable && (
            <span className={styles.error}>
              The deadline is too close: pick another one.
            </span>
          )}
          {showDateMessage && (
            <span className={styles.mutedSmall}>
              We will deliver before the selected date: it already gives us all
              the time we need for quality work at the lowest price.
            </span>
          )}
        </div>
      )}

      {outsourceConfirmed && (
        <div className={styles.confirmed} role="status">
          <strong>Order sent correctly</strong>
          <span>
            Thank you for choosing our outsource service. An account manager
            will soon contact you to send you an invoice.
          </span>
        </div>
      )}

      {!errorQuote && (
        <div className={styles.total}>
          <div className={styles.totalRow}>
            <span className={styles.totalPrice}>
              {`${priceCurrencySymbol} ${formatWithCommas(price)}`}
            </span>
            <DropdownMenu
              triggerMode={DROPDOWN_MENU_TRIGGER_MODE.CLICK}
              portalTarget={panelNode}
              toggleButtonProps={{
                children: (
                  <>
                    {`= ${priceCurrencySymbol} ${pricePWord} / word`}
                    <ChevronDown size={12} />
                  </>
                ),
                // The per-word price is the currency switch, as in the old form
                'aria-label': `Change currency (now ${chunkQuote.get('currency') ?? 'EUR'})`,
                mode: BUTTON_MODE.LINK,
                size: BUTTON_SIZE.LINK_MEDIUM,
                className: styles.perWord,
              }}
              items={Object.keys(currencies).map((key) => ({
                label: currencies[key].name,
                onClick: () => handleCurrencyChange(key),
              }))}
            />
          </div>
          <button
            type="button"
            className={styles.order}
            onClick={
              outsourceConfirmed && jobOutsourced
                ? openOutsourcePage
                : sendOutsource
            }
          >
            {!outsourceConfirmed
              ? 'Order now'
              : !jobOutsourced
                ? 'Confirm'
                : 'View status'}
          </button>
        </div>
      )}

      <p className={styles.contact}>
        Have a specific request? <a href="tel:+390690254001">Call us</a>,{' '}
        <a href="mailto:info@matecat.com">send an email</a> or{' '}
        <button
          type="button"
          className={styles.inlineLink}
          onClick={() => CommonUtils.dispatchCustomEvent(CHAT_EVENT)}
        >
          open chat
        </button>
        .
      </p>
      {form}
    </div>
  )
}

OutsourceOrderPanel.propTypes = {
  // Immutable job of project.jobs
  job: PropTypes.object.isRequired,
  // Immutable project
  project: PropTypes.object.isRequired,
  standardWC: PropTypes.oneOfType([PropTypes.number, PropTypes.string]),
  serviceLoginUrl: PropTypes.string,
  // Share quotes with the other quote users of the page (analysis page)
  cache: PropTypes.bool,
  outsourceEnabled: PropTypes.oneOfType([
    PropTypes.bool,
    PropTypes.number,
    PropTypes.string,
  ]),
}

export default OutsourceOrderPanel
