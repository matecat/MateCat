$(document).ready(function () {
  if (!config.isLoggedIn) $('body').addClass('isAnonymous')

  $('.supported-file-formats').click(function (e) {
    e.preventDefault()
    $('.supported-formats').show()
  })
  $('.close, .grayed').click(function (e) {
    e.preventDefault()
    $('.grayed').fadeOut()
    $('.popup').fadeOut('fast')
  })
  $('#deselectMultilang').click(function (e) {
    e.preventDefault()
    $('.listlang li.on input[type=checkbox]').click()
  })
  $('#swaplang').click(function (e) {
    e.preventDefault()
    var src = $('#source-lang').dropdown('get value')
    var trg = $('#target-lang').dropdown('get value')
    if (trg.split(',').length > 1) {
      APP.alert({
        msg:
          'Cannot swap languages when <br>multiple target languages are selected!',
      })
      return false
    }
    $('#source-lang').dropdown('set selected', trg)
    $('#target-lang').dropdown('set selected', src)

    APP.changeTargetLang(src)

    if ($('.template-download').length) {
      if (UI.conversionsAreToRestart()) {
        APP.confirm({
          msg: 'Source language changed. The files must be reimported.',
          callback: 'confirmRestartConversions',
        })
      }
    } else if ($('.template-gdrive').length) {
      APP.confirm({
        msg:
          'Source language has been changed.<br/>The files will be reimported.',
        callback: 'confirmGDriveRestartConversions',
      })
    }
  })
})
