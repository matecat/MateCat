window.ReviewExtendedFooter = {
  enabled: function () {
    return Review.type === 'extended-footer'
  },
  type: config.reviewType,
}

if (ReviewExtendedFooter.enabled()) {
  var originalGotoNextSegment = UI.gotoNextSegment
  var originalRender = UI.render

  $.extend(UI, {
    render: function (options) {
      var promise = new $.Deferred().resolve()
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
