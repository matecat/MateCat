import React, {Fragment, useEffect} from 'react'
import PropTypes from 'prop-types'
import {useState} from 'react'
import {SettingsPanelTable} from '../../SettingsPanelTable'
import {MTGlossaryRow} from './MTGlossaryRow'

const COLUMNS_TABLE = [
  {name: 'Activate'},
  {name: 'Name'},
  {name: ''},
  {name: ''},
]

const CREATE_ROW_ID = 'createRow'

const fakeApi = {
  getMMTKeys: ({engineId}) =>
    new Promise((resolve) => {
      setTimeout(() => {
        resolve([
          {
            id: 197983,
            name: 'TM esame',
            has_glossary: true,
          },
          {
            id: 197996,
            name: 'TM corso',
            has_glossary: true,
          },
          {
            id: 198430,
            name: 'MyMemory eb90b0a88870372f2599',
            has_glossary: true,
          },
        ])
      }, 500)
    }),
}

export const MTGlossary = ({id, name}) => {
  const [isShowingRows, setIsShowingRows] = useState(false)
  const [rows, setRows] = useState([])

  useEffect(() => {
    let wasCleanup = false

    fakeApi.getMMTKeys({engineId: id}).then((data) => {
      if (!wasCleanup) {
        setRows(
          data.map(({name, id}) => {
            const row = {
              id,
              name,
              isActive: true,
            }

            return {
              ...row,
              node: <MTGlossaryRow key={row.id} {...{row}} />,
            }
          }),
        )
      }
    })

    return () => (wasCleanup = true)
  }, [id])

  return (
    <div className="mt-glossary">
      <div className="expand-button">
        <button onClick={() => setIsShowingRows((prevState) => !prevState)}>
          ModernMT glossary
        </button>
      </div>
      {isShowingRows && (
        <SettingsPanelTable columns={COLUMNS_TABLE} rows={rows} />
      )}
    </div>
  )
}

MTGlossary.propTypes = {
  id: PropTypes.number.isRequired,
  name: PropTypes.string.isRequired,
}
