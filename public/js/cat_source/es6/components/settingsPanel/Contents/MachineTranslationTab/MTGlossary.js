import React, {useEffect} from 'react'
import PropTypes from 'prop-types'
import {useState} from 'react'
import {SettingsPanelTable} from '../../SettingsPanelTable'
import {MTGlossaryRow} from './MTGlossaryRow'
import Upload from '../../../../../../../img/icons/Upload'
import {MTGlossaryCreateRow} from './MTGlossaryCreateRow'
import {getMMTKeys} from '../../../../api/getMMTKeys/getMMTKeys'
import {getStatusMemoryGlossaryImport} from '../../../../api/getStatusMemoryGlossaryImport/getStatusMemoryGlossaryImport'

const COLUMNS_TABLE = [
  {name: 'Activate'},
  {name: 'Name'},
  {name: ''},
  {name: ''},
]

export const MT_GLOSSARY_CREATE_ROW_ID = 'createRow'

export class MTGlossaryStatus {
  constructor() {
    this.wasAborted = false
  }

  get(props, promise = getStatusMemoryGlossaryImport) {
    this.wasAborted = false
    return new Promise((resolve, reject) => {
      this.executeApi({promise, props, resolve, reject})
    })
  }

  cancel() {
    this.wasAborted = true
  }

  executeApi({promise, props, resolve, reject}) {
    const DELAY = 1000

    promise(props).then((data) => {
      if (typeof data?.progress === 'undefined') {
        reject()
        return
      }
      console.log('polling result', data)
      if (data.progress === 0) {
        setTimeout(() => {
          if (!this.wasAborted)
            this.executeApi({promise, props, resolve, reject})
        }, DELAY)
      } else {
        resolve(data)
      }
    })
  }
}

export const MTGlossary = ({id}) => {
  const [isShowingRows, setIsShowingRows] = useState(false)
  const [rows, setRows] = useState([])
  const [isGlossaryCaseSensitive, setIsGlossaryCaseSensitive] = useState(true)

  useEffect(() => {
    let wasCleanup = false

    getMMTKeys({engineId: id}).then((data) => {
      if (!wasCleanup) {
        setRows(
          data.map(({name, id: idRow}) => {
            const row = {
              id: idRow,
              name,
              isActive: false,
            }

            return {
              ...row,
              node: (
                <MTGlossaryRow key={row.id} {...{engineId: id, row, setRows}} />
              ),
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
      isActive: false,
      className: 'row-content-create-glossary',
    }

    setRows((prevState) => [
      ...prevState.filter(({id}) => id !== MT_GLOSSARY_CREATE_ROW_ID),
      {...row, node: <MTGlossaryCreateRow {...{engineId: id, row, setRows}} />},
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
          <SettingsPanelTable
            columns={COLUMNS_TABLE}
            rows={rows}
            className="mt-glossary-table"
          />
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
}