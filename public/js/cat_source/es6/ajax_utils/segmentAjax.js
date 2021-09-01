import {sprintf} from 'sprintf-js'

import {getMatecatApiDomain} from '../utils/getMatecatApiDomain'
import TagUtils from '../utils/tagUtils'
import TextUtils from '../utils/textUtils'

if (!window.API) {
  window.API = {}
}

API.SEGMENT = {
  setTranslation: function (segment) {
    var contextBefore = UI.getContextBefore(segment.sid)
    var idBefore = UI.getIdBefore(segment.sid)
    var contextAfter = UI.getContextAfter(segment.sid)
    var idAfter = UI.getIdAfter(segment.sid)
    var trans = TagUtils.prepareTextToSend(segment.translation)
    var time_to_edit = new Date() - UI.editStart
    // var id_translator = config.id_translator;

    var data = {
      id_segment: segment.sid,
      id_job: config.id_job,
      password: config.password,
      status: segment.status,
      translation: trans,
      segment: segment.segment,
      propagate: false,
      context_before: contextBefore,
      id_before: idBefore,
      context_after: contextAfter,
      id_after: idAfter,
      time_to_edit: time_to_edit,
      revision_number: config.revisionNumber,
      current_password: config.currentPassword,
      // id_translator: id_translator,
    }
    return $.ajax({
      data: data,
      type: 'POST',
      xhrFields: {withCredentials: true},
      url: getMatecatApiDomain() + '?action=setTranslation',
    })
  },

  approveSegments: function (segments) {
    var data = {
      segments_id: segments,
      status: 'approved',
      client_id: config.id_client,
      revision_number: config.revisionNumber,
    }
    return $.ajax({
      async: true,
      data: data,
      type: 'post',
      xhrFields: {withCredentials: true},
      url:
        getMatecatApiDomain() +
        'api/v2/jobs/' +
        config.id_job +
        '/' +
        config.password +
        '/segments/status',
    })
  },
  translateSegments: function (segments) {
    var data = {
      segments_id: segments,
      status: 'translated',
      client_id: config.id_client,
      revision_number: config.revisionNumber,
    }
    return $.ajax({
      async: true,
      data: data,
      type: 'post',
      xhrFields: {withCredentials: true},
      url:
        getMatecatApiDomain() +
        'api/v2/jobs/' +
        config.id_job +
        '/' +
        config.password +
        '/segments/status',
    })
  },

  splitSegment: function (sid, source) {
    var data = {
      segment: source,
      id_segment: sid,
      id_job: config.id_job,
      password: config.password,
    }
    return $.ajax({
      async: true,
      data: data,
      type: 'post',
      xhrFields: {withCredentials: true},
      url: getMatecatApiDomain() + '?action=setSegmentSplit',
    })
  },

  getConcordance: function (query, type) {
    var data = {
      action: 'getContribution',
      is_concordance: 1,
      from_target: type,
      id_segment: UI.currentSegmentId,
      text: TextUtils.view2rawxliff(query),
      id_job: config.job_id,
      num_results: UI.numMatchesResults,
      id_translator: config.id_translator,
      password: config.password,
      id_client: config.id_client,
      current_password: config.currentPassword,
    }
    return $.ajax({
      async: true,
      data: data,
      type: 'post',
      xhrFields: {withCredentials: true},
      url: getMatecatApiDomain() + '?action=getContribution',
    })
  },

  /**
   * Return a list of contribution from a id_segment
   * @param id_segment
   * @param target
   * @return Contributions - Promise
   */
  getContributions: function (id_segment, target) {
    var contextBefore = UI.getContextBefore(id_segment)
    var idBefore = UI.getIdBefore(id_segment)
    var contextAfter = UI.getContextAfter(id_segment)
    var idAfter = UI.getIdAfter(id_segment)
    // check if this function is ok for al cases
    let txt = TagUtils.prepareTextToSend(target)
    let data = {
      action: 'getContribution',
      password: config.password,
      is_concordance: 0,
      id_segment: id_segment,
      text: txt,
      id_job: config.id_job,
      num_results: UI.numContributionMatchesResults,
      id_translator: config.id_translator,
      context_before: contextBefore,
      id_before: idBefore,
      context_after: contextAfter,
      id_after: idAfter,
      id_client: config.id_client,
      current_password: config.currentPassword,
    }

    return $.ajax({
      async: true,
      data: data,
      type: 'post',
      xhrFields: {withCredentials: true},
      url: getMatecatApiDomain() + '?action=getContribution',
    })
  },
}
