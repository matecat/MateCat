import React from 'react'

const JobTableHeader = ({rates}) => {
  return (
    <div className="job-table-header">
      <div className="job-table-header-title">
        <div>Analysis bucket</div>
        <div>Payable rates</div>
      </div>
      <table>
        <thead>
          <tr>
            <th>New</th>
            <th>Repetitions</th>
            <th>Internal 75-99%</th>
            <th>TM Partial 50-74%</th>
            <th>TM Partial 75-84%</th>
            <th>TM Partial 85-94%</th>
            <th>TM Partial 95-99%</th>
            <th>TM 100%</th>
            <th>Public TM 100%</th>
            <th>TM 100% in context</th>
            <th>Machine Translation</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>{rates.NO_MATCH}%</td>
            <td>{rates.REPETITIONS}%</td>
            <td>{rates.INTERNAL}%</td>
            <td>{rates['50%-74%']}%</td>
            <td>{rates['75%-84%']}%</td>
            <td>{rates['85%-94%']}%</td>
            <td>{rates['95%-99%']}%</td>
            <td>{rates['100%']}%</td>
            <td>{rates['100%_PUBLIC']}%</td>
            <td>{rates['ICE'] ? rates['ICE'] : 0}%</td>
            <td>{rates.MT}%</td>
          </tr>
        </tbody>
      </table>
      <div className="job-table-header-total">
        <div>Total</div>
      </div>
    </div>
  )
}

export default JobTableHeader
