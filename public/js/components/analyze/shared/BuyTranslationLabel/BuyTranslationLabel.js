import React from 'react'
import PropTypes from 'prop-types'
import TranslatedIconSmall from '../../../../../img/icons/TranslatedIconSmall'
import styles from './BuyTranslationLabel.module.scss'

/**
 * "Buy translation from" and the Translated logo, as Manage's button has it.
 * The logo takes the text colour; it is always read as "Buy translation from
 * Translated".
 */
export const BuyTranslationLabel = ({compact = false}) => (
  <span className={styles.label}>
    {compact ? 'Buy translation' : 'Buy translation from'}
    <span
      className={styles.logo}
      aria-hidden="true"
      data-testid="translated-logo"
    >
      <TranslatedIconSmall size={18} />
    </span>
    <span className={styles.hidden}>
      {compact ? ' from Translated' : ' Translated'}
    </span>
  </span>
)

BuyTranslationLabel.propTypes = {
  // Without "from" (the name still says it): for the narrow tree rows
  compact: PropTypes.bool,
}
