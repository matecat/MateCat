import React, {useState, useEffect, useRef} from 'react'
import {checkSplitRequest} from '../../api/checkSplitRequest'
import {confirmSplitRequest} from '../../api/confirmSplitRequest'
import CommonUtils from '../../utils/commonUtils'
import ModalsActions from '../../actions/ModalsActions'
import {
  Button,
  BUTTON_MODE,
  BUTTON_SIZE,
  BUTTON_TYPE,
} from '../common/Button/Button'
import Tooltip from '../common/Tooltip'

const SplitJobModal = ({job, project, callback}) => {
  const [numSplit, setNumSplit] = useState(2)
  const [wordsArray, setWordsArray] = useState(null)
  const [splitChecked, setSplitChecked] = useState(false)
  const [showLoader, setShowLoader] = useState(false)
  const [showStartLoader, setShowStartLoader] = useState(true)
  const [showError, setShowError] = useState(false)
  const [total, setTotal] = useState(0)
  const [errorMsg, setErrorMsg] = useState('')
  const [splitRawWord, setSplitRawWords] = useState(
    job.get('stats').get('equivalent').get('total') === 0,
  )
  const [splitData, setSplitData] = useState()

  const splitSelectRef = useRef(null)
  const tooltipRef = useRef()

  useEffect(() => {
    getSplitData()
  }, [])

  useEffect(() => {
    checkSplitData()
  }, [splitData, splitRawWord])

  const checkSplitData = () => {
    let arrayChunks = []
    if (splitData && splitData.chunks) {
      let total
      if (!splitRawWord) {
        total = splitData.eq_word_count
      } else {
        total = splitData.raw_word_count
      }

      splitData.chunks.forEach((item, index) => {
        if (typeof splitData.chunks[index] === 'undefined') {
          arrayChunks[index] = 0
        } else {
          if (splitRawWord) {
            arrayChunks[index] = parseInt(
              splitData.chunks[index].raw_word_count,
            )
          } else {
            arrayChunks[index] = parseInt(splitData.chunks[index].eq_word_count)
          }
        }
      })
      if (parseInt(splitData.eq_word_count) === 0) {
        setSplitRawWords(true)
      }
      setWordsArray(arrayChunks)
      setTotal(total)
      setShowStartLoader(false)
      setSplitChecked(true)
      setShowLoader(false)
    }
  }

  const getSplitData = () => {
    checkSplitRequest(
      job.toJS(),
      project.toJS(),
      numSplit,
      wordsArray,
      splitRawWord,
    )
      .then(({data}) => {
        setSplitData(data)
      })
      .catch((errors) => {
        if (errors !== 'undefined' && errors.length) {
          setErrorMsg(errors[0].message)
          setShowError(true)
          setShowLoader(false)
          setShowStartLoader(false)
          setSplitChecked(false)
        }
      })
  }

  const closeModal = () => {
    ModalsActions.onCloseModal()
  }

  const changeSplitNumber = () => {
    const arraySplitNew = calculateSplitComputation(
      splitSelectRef.current.value,
    )
    setNumSplit(splitSelectRef.current.value)
    setWordsArray(arraySplitNew)
    setSplitChecked(false)
    setShowLoader(false)
  }

  const calculateSplitComputation = (numSplit) => {
    let numWords,
      array = []
    let totalInt = Math.round(total)

    let wordsXjob = Math.floor(totalInt / numSplit)
    let diff = totalInt - wordsXjob * numSplit
    for (let i = 0; i < numSplit; i++) {
      numWords = wordsXjob
      if (i < diff) {
        numWords++
      }

      array.push(numWords)
    }
    return array
  }

  const changeInputWordsCount = (indexChanged, e) => {
    let value = e.target.value !== '' ? parseInt(e.target.value) : 0
    setWordsArray((prevValue) =>
      prevValue.map((item, i) => {
        if (i === indexChanged) return value
        else return item
      }),
    )
    setSplitChecked(false)
    setShowLoader(false)
  }

  const checkSplitComputation = () => {
    if (!wordsArray) {
      return null
    }
    let sum = wordsArray.reduce((a, b) => a + b, 0)
    let diff = sum - Math.round(total)
    if (diff !== 0) {
      return {
        difference: diff,
        sum: sum,
      }
    }
  }

  const checkSplitJob = () => {
    setShowLoader(true)
    checkSplitRequest(
      job.toJS(),
      project.toJS(),
      numSplit,
      wordsArray,
      splitRawWord,
    )
      .then(({data}) => {
        setSplitData(data)
      })
      .catch((errors) => {
        if (typeof errors !== 'undefined' && errors.length) {
          setErrorMsg(errors[0].message)
          setShowError(true)
          setShowLoader(false)
          setSplitChecked(false)
        }
      })
  }

  const confirmSplitJob = () => {
    setShowLoader(true)
    let array = wordsArray.filter((item) => item > 0)

    confirmSplitRequest(
      job.toJS(),
      project.toJS(),
      array.length,
      array,
      splitRawWord,
    )
      .then((d) => {
        if (d.data && d.data.chunks) {
          callback()
          ModalsActions.onCloseModal()
        }
      })
      .catch((errors) => {
        if (typeof errors !== 'undefined' && errors.length) {
          setErrorMsg(errors[0].message)
          setShowError(true)
          setShowLoader(false)
          setSplitChecked(false)
        }
      })
  }

  const getJobParts = () => {
    if (!wordsArray) {
      return (
        <div className="ui segment" style={{height: '126px'}}>
          <div className="ui active inverted dimmer">
            <div className="ui text loader">Loading</div>
          </div>
        </div>
      )
    }
    return Array.from({length: numSplit}, (_, i) => {
      let value =
        wordsArray[i] && parseInt(wordsArray[i]) !== 0 ? wordsArray[i] : 0
      let disableClass = value > 0 ? '' : 'void'
      let emptyClass = value === 0 && splitChecked ? 'empty' : ''
      return (
        <li key={'split-' + i} className={disableClass}>
          <div>
            <h4>Chunk {i + 1}</h4>
          </div>
          <div className="job-details">
            <div className="job-perc">
              <p>
                {!splitChecked ? (
                  <span className="aprox">Approx. words:</span>
                ) : (
                  ''
                )}
                <span className="correct none">Words:</span>
              </p>
              <input
                type="text"
                className={'input-small ' + emptyClass}
                value={value}
                onChange={(e) => changeInputWordsCount(i, e)}
              />
            </div>
          </div>
        </li>
      )
    })
  }

  let splitParts = getJobParts()
  let checkSplit = checkSplitComputation()
  let showSplitDiffError = !!checkSplit
  let errorLabel =
    checkSplit && checkSplit.difference < 0
      ? 'Words remaining'
      : 'Words exceeding'
  let totalWords = Math.round(total)

  return (
    <div className="modal popup-split">
      <div id="split-modal-cont">
        <div className="splitbtn-cont">
          <div>
            <h3>
              <span className="popup-split-job-id">ID: {job.get('id')} </span>
              <span className="popup-split-job-title">
                {job.get('sourceTxt') + ' > ' + job.get('targetTxt')}
              </span>
            </h3>
          </div>
          <div>
            <div className="split-checkbox">
              <span>Split based on raw word count</span>
              {splitData && parseInt(splitData.eq_word_count) !== 0 ? (
                <input
                  type="checkbox"
                  checked={splitRawWord}
                  onChange={(e) => setSplitRawWords(e.target.checked)}
                />
              ) : (
                <Tooltip
                  content={
                    'The weighted word count for this job is 0, splitting is only available based on raw word count.'
                  }
                >
                  <input
                    ref={tooltipRef}
                    type="checkbox"
                    checked="false"
                    disabled="true"
                  />
                </Tooltip>
              )}
            </div>
            <div className="container-split-select">
              <div className="label left">Split job in:</div>
              <select
                name="popup-splitselect"
                className="splitselect left"
                ref={splitSelectRef}
                onChange={changeSplitNumber}
              >
                {Array.from({length: 49}, (_, i) => (
                  <option key={i + 2} value={i + 2}>
                    {i + 2}
                  </option>
                ))}
              </select>
              <div className="label left">Chunks</div>
            </div>
          </div>
        </div>
        <div className="popup-box split-box3">
          <ul className="jobs">{splitParts}</ul>
          <div className="total">
            <p className="wordsum">
              Total words: <span className="total-w">{totalWords}</span>
            </p>
            {showSplitDiffError && (
              <p className="error-count current">
                Current count:{' '}
                <span className="curr-w">
                  {CommonUtils.addCommas(checkSplit.sum)}
                </span>
              </p>
            )}
            {showSplitDiffError && (
              <p className="error-count">
                <span className="txt">{errorLabel}</span>:{' '}
                <span className="diff-w">
                  {Math.abs(checkSplit.difference)}
                </span>
              </p>
            )}
          </div>
        </div>
        <div className="popup-box split-box4">
          {showError && (
            <div className="error">
              <span className="err-msg">{errorMsg}</span>
            </div>
          )}
          <Button
            mode={BUTTON_MODE.OUTLINE}
            size={BUTTON_SIZE.MEDIUM}
            onClick={closeModal}
          >
            Cancel
          </Button>
          {!showSplitDiffError && splitChecked && (
            <Button
              type={BUTTON_TYPE.PRIMARY}
              size={BUTTON_SIZE.MEDIUM}
              onClick={confirmSplitJob}
            >
              Confirm
            </Button>
          )}
          {!splitChecked && !showStartLoader && (
            <Button
              type={BUTTON_TYPE.PRIMARY}
              size={BUTTON_SIZE.MEDIUM}
              onClick={checkSplitJob}
            >
              Check split
            </Button>
          )}
          {showLoader && (
            <div className="loader">
              <i className="fa fa-spinner fa-spin"></i>
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default SplitJobModal
