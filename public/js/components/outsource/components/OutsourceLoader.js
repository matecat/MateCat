import React from 'react'

const OutsourceLoader = ({translatorsNumber}) => {
  let msg = 'Choosing the best available translator...'
  if (translatorsNumber && parseInt(translatorsNumber.asInt) > 30) {
    msg = `Choosing the best available translator from the matching ${translatorsNumber.printable}...`
  }

  return (
    <div className="translated-loader">
      <img src="../../public/img/loader-matecat-translated-outsource.gif" />
      <div className="text-loader-outsource">{msg}</div>
    </div>
  )
}

export default OutsourceLoader

