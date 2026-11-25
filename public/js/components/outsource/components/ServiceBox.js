import React from 'react'
import TranslatedIcon from '../../../../img/icons/TranslatedIcon'

const ServiceBox = ({revision, compact = false}) => (
  <div className="payment-service">
    <div className="service-box">
      <div className="project-management">Outsource: </div>
      <div className="translation">Project Management + Translation </div>
      {(revision || compact) && <div className="revision"> + Revision</div>}
    </div>
    {!compact && (
      <div className="fiducial-logo">
        Guaranteed by
        <TranslatedIcon size={16} />
        Translated
      </div>
    )}
  </div>
)

export default ServiceBox
