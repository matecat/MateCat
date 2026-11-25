import React, {useState, useEffect, useRef} from 'react'
import {checkSplitRequest} from '../../api/checkSplitRequest'
import {confirmSplitRequest} from '../../api/confirmSplitRequest'
import CommonUtils from '../../utils/commonUtils'
import {getApiErrorMessage} from '../../utils/getApiErrorMessage'
import ModalsActions from '../../actions/ModalsActions'
import {Button, BUTTON_MODE, BUTTON_TYPE} from '../common/Button/Button'
import Tooltip from '../common/Tooltip'
import {SpinnerLoader} from '../common/SpinnerLoader'
import {Checkbox, CHECKBOX_STATE} from '../common/Checkbox'
import {NumericStepper} from '../common/NumericStepper/NumericStepper'

const SplitJobModal = ({job, project, callback}) => {
  const [numSplit, setNumSplit] = useState(2)
  const [wordsArray, setWordsArray] = useState(null)
  const [splitChecked, setSplitChecked] = useState(false)
  const [showLoader, setShowLoader] = useState(true)
  const [showStartLoader, setShowStartLoader] = useState(true)
  const [showError, setShowError] = useState(false)
  const [total, setTotal] = useState(0)
  const [errorMsg, setErrorMsg] = useState('')
  const [splitRawWord, setSplitRawWords] = useState(
    job.get('stats').get('equivalent').get('total') === 0,
  )
  const [splitData, setSplitData] = useState()
  const [jobSplttable, setJobSplittable] = useState(true)
  const [isConfirming, setIsConfirming] = useState(false)

  const isConfirmingRef = useRef(false)
  const tooltipRef = useRef()

  useEffect(() => {
    getSplitData()
  }, [])

  useEffect(() => {
    checkSplitData()
  }, [splitData, splitRawWord])

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

  // Only runs when the stepper's value is committed (+/-, blur, Enter), not on
  // every keystroke — mirrors the old <select onChange> firing only on an
  // actual selection. Recomputing on every `total`/`showLoader` change (as a
  // useEffect watching them would) reset splitChecked right back to false the
  // moment checkSplitData() had just set it to true.
  const changeSplitNumber = (newNumSplit) => {
    const arraySplitNew = calculateSplitComputation(newNumSplit)
    setNumSplit(newNumSplit)
    setWordsArray(arraySplitNew)
    setSplitChecked(false)
    setShowLoader(false)
  }

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
      .catch(({errors}) => {
        if (errors !== 'undefined' && errors.length) {
          if (errors[0].code === -7) {
            setJobSplittable(false)
            setErrorMsg('Split unsuccessful: the job has too few segments.')
          } else {
            setErrorMsg(errors[0].message)
          }
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
      .catch(({errors}) => {
        if (typeof errors !== 'undefined' && errors.length) {
          setErrorMsg(errors[0].message)
          setShowError(true)
          setShowLoader(false)
          setSplitChecked(false)
        }
      })
  }

  const releaseConfirm = () => {
    isConfirmingRef.current = false
    setIsConfirming(false)
    setShowLoader(false)
  }

  const confirmSplitJob = () => {
    if (isConfirmingRef.current) return
    isConfirmingRef.current = true
    setIsConfirming(true)
    setShowLoader(true)
    let array = wordsArray.filter((item) => item > 0)

    confirmSplitRequest(
      job.toJS(),
      project.toJS(),
      array.length,
      array,
      splitRawWord,
    )
      // Two-argument then: an error thrown by callback() after a successful split must not
      // reach the failure handler, which would enable Confirm again on a job already split.
      .then(
        (d) => {
          if (d.data && d.data.chunks) {
            callback()
            ModalsActions.onCloseModal()
          } else {
            releaseConfirm()
          }
        },
        // Show the server's message, for example a 409 when another request is changing this job.
        (rejection) => {
          if (rejection?.errors) {
            setErrorMsg(getApiErrorMessage(rejection))
            setShowError(true)
            setShowLoader(false)
            setSplitChecked(false)
          }
          releaseConfirm()
        },
      )
  }

  const getJobParts = () => {
    if (!jobSplttable) {
      return <div className="split-loader" style={{height: '126px'}}></div>
    } else if (!wordsArray) {
      return (
        <div className="split-loader" style={{height: '126px'}}>
          <SpinnerLoader />
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
              {splitData && parseInt(splitData.eq_word_count) !== 0 ? (
                <Checkbox
                  label="Split based on raw word count"
                  value={
                    splitRawWord
                      ? CHECKBOX_STATE.CHECKED
                      : CHECKBOX_STATE.UNCHECKED
                  }
                  onChange={(checked) => setSplitRawWords(checked)}
                />
              ) : (
                <Tooltip
                  content={
                    'The weighted word count for this job is 0, splitting is only available based on raw word count.'
                  }
                >
                  <div ref={tooltipRef}>
                    <Checkbox
                      label="Split based on raw word count"
                      value={CHECKBOX_STATE.UNCHECKED}
                      onChange={() => {}}
                      disabled
                    />
                  </div>
                </Tooltip>
              )}
            </div>
            <div className="container-split-numeric-stepper">
              <div className="label left">Split job in:</div>
              <NumericStepper
                value={numSplit}
                onChange={setNumSplit}
                onChangeCommitted={changeSplitNumber}
                minimumValue={2}
                maximumValue={50}
                stepValue={1}
                disabled={!jobSplttable}
              />
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
          {showLoader && <div className="button-loader show" />}
          <Button mode={BUTTON_MODE.OUTLINE} onClick={closeModal}>
            Cancel
          </Button>
          {!showSplitDiffError && splitChecked && (
            <Button
              type={BUTTON_TYPE.PRIMARY}
              onClick={confirmSplitJob}
              disabled={isConfirming}
            >
              Confirm
            </Button>
          )}
          {!splitChecked && !showStartLoader && (
            <Button
              type={BUTTON_TYPE.PRIMARY}
              onClick={checkSplitJob}
              disabled={!jobSplttable}
            >
              Check split
            </Button>
          )}
        </div>
      </div>
    </div>
  )
}

export default SplitJobModal
