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
  <>
    {chunkQuote.get('t_name') !== '' && (
      <div className={'translator-job-details__title'}>
        Best identified translator for this job:
      </div>
    )}

    <div className="translator-job-details">
      {chunkQuote.get('t_name') !== '' ? (
        <>
          <div className="translator-details">
            <div className="translator-avatar">
              {chunkQuote.get('t_name').charAt(0)}
            </div>
            <div className="translator-info">
              <div>
                <b>{chunkQuote.get('t_name')}</b> by Translated
              </div>
              <div className={'translator-words'}>
                {translatedWords} words translated last 12 months
              </div>
              <div className={'translator-feedback'}>
                {chunkQuote.get('t_experience_years')} years of experience
              </div>
            </div>
          </div>
        </>
      ) : (
        <div className="translator-no-found">
          <p>
            Translated uses the <b>most qualified translator</b> <br /> and{' '}
            <b>keeps using the same translator for your next projects. </b>
          </p>
        </div>
      )}

      <div className="source-target-outsource st-details">
        {job.get('source')}
        <ChevronRight size={16} />
        {job.get('target')}
      </div>
      <div className="job-payment">
        {numberWithCommas(chunkQuote.get('words'))} words
      </div>
      {!outsourceConfirmed && (
        <div className="job-price">
          {priceCurrencySymbol}{' '}
          {formatPriceWithCommas(getCurrencyPrice(chunkQuote.get('price')))}
        </div>
      )}
    </div>
  </>
)

export default TranslatorDetails
