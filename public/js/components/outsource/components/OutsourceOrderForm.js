import React from 'react'
import PropTypes from 'prop-types'

/**
 * The hidden form that sends an outsource order to the vendor's login page.
 * useOutsourceOrder fills its fields and submits it.
 */
const OutsourceOrderForm = ({formRef, serviceLoginUrl}) => (
  <form
    id="continueForm"
    action={serviceLoginUrl}
    method="POST"
    target="_blank"
    ref={formRef}
  >
    <input type="hidden" name="url_ok" value="" />
    <input type="hidden" name="url_ko" value="" />
    <input type="hidden" name="confirm_urls" value="" />
    <input type="hidden" name="data_key" value="" />
    <input type="hidden" name="quoteData" value="" />
  </form>
)

OutsourceOrderForm.propTypes = {
  formRef: PropTypes.oneOfType([PropTypes.func, PropTypes.object]).isRequired,
  serviceLoginUrl: PropTypes.string,
}

export default OutsourceOrderForm
