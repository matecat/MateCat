import React, {useEffect} from 'react'
import PropTypes from 'prop-types'
import {useState} from 'react'
import {SettingsPanelTable} from '../../SettingsPanelTable'
import {MTGlossaryRow} from './MTGlossaryRow'
import Upload from '../../../../../../../img/icons/Upload'
import {MTGlossaryCreateRow} from './MTGlossaryCreateRow'

const COLUMNS_TABLE = [
  {name: 'Activate'},
  {name: 'Name'},
  {name: ''},
  {name: ''},
]

export const MT_GLOSSARY_CREATE_ROW_ID = 'createRow'

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
  updateMMTMemory: ({id, name}) =>
    new Promise((resolve) => {
      resolve()
    }),
}

export const MTGlossary = ({id, name}) => {
  const [isShowingRows, setIsShowingRows] = useState(false)
  const [rows, setRows] = useState([])
  const [isGlossaryCaseSensitive, setIsGlossaryCaseSensitive] = useState(false)

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
              node: <MTGlossaryRow key={row.id} {...{row, setRows}} />,
            }
          }),
        )
      }
    })

    return () => (wasCleanup = true)
  }, [id])

  const addGlossary = () => {
    const row = {
      id: MT_GLOSSARY_CREATE_ROW_ID,
      isActive: true,
      className: 'row-content-create-glossary',
    }

    setRows((prevState) => [
      ...prevState.filter(({id}) => id !== MT_GLOSSARY_CREATE_ROW_ID),
      {...row, node: <MTGlossaryCreateRow {...{row, setRows}} />},
    ])
  }

  const onChangeCaseSensitive = (e) =>
    setIsGlossaryCaseSensitive(e.currentTarget.checked)

  return (
    <div className="mt-glossary">
      <div className="expand-button">
        <button onClick={() => setIsShowingRows((prevState) => !prevState)}>
          ModernMT glossary
        </button>
      </div>
      {isShowingRows && (
        <>
          <SettingsPanelTable columns={COLUMNS_TABLE} rows={rows} />
          <div className="bottom-buttons">
            <button className="grey-button" onClick={addGlossary}>
              <Upload size={14} />
              Add glossary
            </button>
            <div className="mt-glossary-case-sensitive">
              <input
                checked={isGlossaryCaseSensitive}
                onChange={onChangeCaseSensitive}
                type="checkbox"
                title=""
              />
              <label>Make glossary case sensitive</label>
            </div>
          </div>
        </>
      )}
    </div>
  )
}

MTGlossary.propTypes = {
  id: PropTypes.number.isRequired,
  name: PropTypes.string.isRequired,
}
