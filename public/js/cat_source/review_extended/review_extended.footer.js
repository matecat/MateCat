window.ReviewExtendedFooter = {
  enabled: function () {
    return Review.type === 'extended-footer'
  },
  type: config.reviewType,
}

if (window.ReviewExtendedFooter.enabled()) {
  let originalGotoNextSegment = UI.gotoNextSegment
  let originalRender = UI.render

  $.extend(UI, {
    render: function (options) {
      let promise = new $.Deferred().resolve()
      originalRender.call(this, options)
      return promise
    },

    setDisabledOfButtonApproved: function (sid, isDisabled) {
      var div = $('#segment-' + sid + '-buttons').find(
        '.approved, .next-unapproved',
      )
      if (!isDisabled) {
        div.removeClass('disabled').attr('disabled', false)
      } else {
        div.addClass('disabled').attr('disabled', false)
      }
    },

    gotoNextSegment: function (sid) {
      if (config.isReview && sid) {
        this.setDisabledOfButtonApproved(sid, true)
      }
      originalGotoNextSegment.apply(this)
      return false
    },
  })
}
