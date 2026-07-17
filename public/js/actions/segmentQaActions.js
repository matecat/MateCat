import {getLocalWarnings} from '../api/getLocalWarnings'
import {getGlossaryCheck} from '../api/getGlossaryCheck'
import {removeTagsFromText} from '../components/segments/utils/DraftMatecatUtils/tagUtils'
import CommonUtils from '../utils/commonUtils'
import {setSegmentWarnings} from './segmentDispatchActions'
import CatToolConstants from '../constants/CatToolConstants'
import CatToolStore from '../stores/CatToolStore'
import OfflineUtils from '../utils/offlineUtils'
import SegmentStore from '../stores/SegmentStore'

export const getSegmentsQa = (segment) => {
  if (!segment) return

  const {status, translation, updatedSource} = segment

  getLocalWarnings({
    id: segment.sid,
    id_job: config.id_job,
    password: config.password,
    src_content: updatedSource,
    trg_content: translation,
    segment_status: status,
    characters_counter: segment.charactersCounter ?? 0,
  })
    .then((data) => {
      if (data.details && data.details.id_segment) {
        setSegmentWarnings(
          data.details.id_segment,
          data.details.issues_info,
          data.details.tag_mismatch,
        )
      } else {
        setSegmentWarnings(segment.original_sid, {}, {})
      }
      CommonUtils.dispatchCustomEvent('getWarning:local:success', {
        resp: data,
        segment: segment,
      })
    })
    .catch(() => {
      OfflineUtils.failedConnection()
    })
  // get tm keys
  new Promise((resolve) => {
    if (!CatToolStore.getJobTmKeys() || !CatToolStore.getHaveKeysGlossary()) {
      let isJobTmKeysCompleted = !!CatToolStore.getJobTmKeys()
      let isHaveKeysGlossaryCompleted = !!CatToolStore.getHaveKeysGlossary()

      const resolvePromise = () =>
        isJobTmKeysCompleted && isHaveKeysGlossaryCompleted && resolve()

      const setJobTmKeys = () => {
        isJobTmKeysCompleted = true
        resolvePromise()

        CatToolStore.removeListener(
          CatToolConstants.UPDATE_TM_KEYS,
          setJobTmKeys,
        )
      }
      const setHaveKeysGlossary = () => {
        isHaveKeysGlossaryCompleted = true
        resolvePromise()

        CatToolStore.removeListener(
          CatToolConstants.HAVE_KEYS_GLOSSARY,
          setHaveKeysGlossary,
        )
      }

      CatToolStore.addListener(CatToolConstants.UPDATE_TM_KEYS, setJobTmKeys)
      CatToolStore.addListener(
        CatToolConstants.HAVE_KEYS_GLOSSARY,
        setHaveKeysGlossary,
      )
    } else {
      resolve()
    }
  }).then(() => {
    const cleanSource = removeTagsFromText(updatedSource)
    const cleanTranslation = removeTagsFromText(translation)
    if (
      CatToolStore.getHaveKeysGlossary() &&
      cleanSource &&
      cleanTranslation
    ) {
      const jobTmKeys = CatToolStore.getJobTmKeys()
      getGlossaryCheck({
        idSegment: segment.sid,
        target: cleanTranslation,
        source: cleanSource,
        keys: jobTmKeys.map(({key}) => key),
      }).catch((error) => {
        console.log('Glossary check failed', error)
      })
    }
  })
}

let pendingQACheck

export const startSegmentQACheck = () => {
  clearTimeout(pendingQACheck)
  pendingQACheck = setTimeout(function () {
    getSegmentsQa(SegmentStore.getCurrentSegment())
  }, config.segmentQACheckInterval)
}
