import React from 'react'

const ConfirmDelivery = ({
  outsourceConfirmed,
  jobOutsourced,
  email,
  onGoBack,
  extendedMessage = false,
}) => {
  if (outsourceConfirmed && !jobOutsourced) {
    return (
      <div className="confirm-delivery-input">
        <div className="back" onClick={onGoBack}>
          <a className="outsource-goBack">
            <i className="icon-chevron-left icon" />
            Back
          </a>
        </div>
        <div className="email-confirm">
          {extendedMessage
            ? "Insert your email and we'll start working on your project instantly."
            : 'Great, an Account Manager will contact you to send you the invoice as a customer to this email'}
        </div>
        <div className="ui input">
          <input
            type="text"
            placeholder="Insert email"
            defaultValue={email}
          />
        </div>
      </div>
    )
  }

  if (outsourceConfirmed && jobOutsourced) {
    return (
      <div className="confirm-delivery-box">
        <div className="confirm-title">Order sent correctly</div>
        <p>
          Thank you for choosing our Outsource service
          {extendedMessage && (
            <>
              <br />
              You will soon be contacted by a Account Manager to send you an
              invoice
            </>
          )}
          {!extendedMessage && '.'}
        </p>
      </div>
    )
  }

  return null
}

export default ConfirmDelivery

