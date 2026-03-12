import React from 'react'
import {numberWithCommas, formatPriceWithCommas} from '../outsourceConstants'
import ChevronRight from '../../../../img/icons/ChevronRight'

const TranslatorDetails = ({
  chunkQuote,
  translatedWords,
  job,
  outsourceConfirmed,
  priceCurrencySymbol,
  getCurrencyPrice,
}) => (
  <div className="translator-job-details">
    {chunkQuote.get('t_name') !== '' ? (
      <>
        <div className="translator-details-box">
          <div>Best identified translator for this job:</div>
          <div className="translator-details">
            <div className="translator-avatar">
              {chunkQuote.get('t_name').charAt(0)}
            </div>
            <div className="translator-info">
              <div>
                <b>{chunkQuote.get('t_name')}</b> by Translated
              </div>
              <div>{translatedWords} words translated last 12 months</div>
              <div>
                {chunkQuote.get('t_experience_years')} years of experience
              </div>
            </div>
          </div>
        </div>
      </>
    ) : (
      <div className="translator-details-box">
        <div className="translator-no-found">
          <p>
            Translated uses the <b>most qualified translator</b> <br /> and{' '}
            <b>keeps using the same translator for your next projects. </b>
          </p>
        </div>
      </div>
    )}

    <div className="job-details-box">
      <div className="source-target-outsource st-details">
        <div className="source-box">{job.get('source')}</div>
        <div className="in-to">
          <ChevronRight size={16} />
        </div>
        <div className="target-box">{job.get('target')}</div>
      </div>
    </div>
    <div className="job-payment">
      <div className="payable">
        {numberWithCommas(chunkQuote.get('words'))} words
      </div>
    </div>
    {!outsourceConfirmed && (
      <div className="job-price">
        {priceCurrencySymbol}{' '}
        {formatPriceWithCommas(getCurrencyPrice(chunkQuote.get('price')))}
      </div>
    )}
  </div>
)

export default TranslatorDetails
