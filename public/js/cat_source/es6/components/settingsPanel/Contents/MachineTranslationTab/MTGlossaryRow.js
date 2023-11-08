import React, {Fragment} from 'react'

export const MTGlossaryRow = ({row}) => {
  const {isActive, name} = row

  return (
    <Fragment>
      <div className="align-center">
        <input checked={isActive} type="checkbox" title="" />
      </div>
      <div>
        <input
          className="glossary-name"
          value={name}
          //   onBlur={updateKeyName}
        />
      </div>
      <div>cl3</div>
      <div>cl4</div>
    </Fragment>
  )
}
