import React from 'react'
import PropTypes from 'prop-types'
import {Pending} from '../Pending'
import {useValueFlash, VALUE_FLASH_CLASS} from '../useValueFlash'
import styles from './SavingCard.module.scss'

/**
 * "Saving on word count" card. While the analysis runs (`live`) the whole card
 * flashes each time the saving changes.
 */
export const SavingCard = ({savingPercent, workMinutesLabel, known, live}) => {
  const flashing = useValueFlash(savingPercent, {enabled: live && known})
  const percentKnown = known && Number.isFinite(savingPercent)

  const className = [
    styles.card,
    VALUE_FLASH_CLASS.base,
    flashing && VALUE_FLASH_CLASS.active,
  ]
    .filter(Boolean)
    .join(' ')

  return (
    <section className={className} data-testid="saving-card">
      <div className={styles.percent}>
        <Pending known={percentKnown}>{`${savingPercent}%`}</Pending>
      </div>
      <div className={styles.content}>
        <h2 className={styles.title}>Saving on word count</h2>
        <div className={styles.work}>
          <Pending known={known}>{workMinutesLabel}</Pending>
        </div>
      </div>
    </section>
  )
}

SavingCard.propTypes = {
  savingPercent: PropTypes.number,
  // e.g. "2 work hours at 3,000 w/day"
  workMinutesLabel: PropTypes.string,
  known: PropTypes.bool.isRequired,
  live: PropTypes.bool,
}
