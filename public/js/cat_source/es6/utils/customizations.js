import Cookies from 'js-cookie'

import SegmentUtils from './segmentUtils'

const Customizations = {
  custom: {},

  loadCustomization() {
    if (Cookies.get('user_customization')) {
      this.custom = $.parseJSON(Cookies.get('user_customization'))
    } else {
      this.custom = {
        extended_tagmode: true,
      }
      this.saveCustomization()
    }
    //Tag Projection: the tag-mode is always extended
    if (SegmentUtils.checkTPEnabled()) {
      // Disable Tag Crunched Mode
      this.custom.extended_tagmode = true
    }
    this.setTagMode()
  },

  toggleTagsMode() {
    if (UI.body.hasClass('tagmode-default-compressed')) {
      this.setExtendedTagMode()
    } else {
      this.setCrunchedTagMode()
    }
  },

  setTagMode() {
    if (this.custom.extended_tagmode) {
      this.setExtendedTagMode()
    } else {
      this.setCrunchedTagMode()
    }
  },
  setExtendedTagMode: function () {
    UI.body.removeClass('tagmode-default-compressed')
    $('.tagModeToggle').addClass('active')
    this.custom.extended_tagmode = true
    this.saveCustomization()
  },
  setCrunchedTagMode: function () {
    UI.body.addClass('tagmode-default-compressed')
    $('.tagModeToggle').removeClass('active')
    this.custom.extended_tagmode = false
    this.saveCustomization()
  },

  saveCustomization: function () {
    Cookies.set('user_customization', JSON.stringify(this.custom), {
      expires: 3650,
      secure: true,
    })
  },
}

export default Customizations
