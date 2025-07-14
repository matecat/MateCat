import React from 'react'

export const SegmentFooterTabError = () => {
  return (
    <div className="engine-errors">
      <span>
        We are currently unable to provide access to language resources due to
        connection issues.
        <br />
        Please refresh the page and, if the issues persists, please refer to
        this{' '}
        <a
          href="https://guides.matecat.com/tm-matches-mt-not-working"
          target="_blank"
        >
          support page
        </a>
        .
      </span>
    </div>
  )
}
