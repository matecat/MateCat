$(function () {
  function b64toBlob(b64Data, contentType, sliceSize) {
    contentType = contentType || ''
    sliceSize = sliceSize || 512
    var byteCharacters = atob(b64Data)
    var byteArrays = []
    for (var offset = 0; offset < byteCharacters.length; offset += sliceSize) {
      var slice = byteCharacters.slice(offset, offset + sliceSize)
      var byteNumbers = new Array(slice.length)
      for (var i = 0; i < slice.length; i++) {
        byteNumbers[i] = slice.charCodeAt(i)
      }
      var byteArray = new Uint8Array(byteNumbers)
      byteArrays.push(byteArray)
    }
    var blob = new Blob(byteArrays, {
      type: contentType,
    })
    return blob
  }

  $('#fileupload').fileupload({
    autoUpload: true,
    acceptFileTypes: config.allowedFileTypes,
    dataType: config.dataType,
    maxFileSize: config.maxFileSize,

    add: function (e, data) {
      conversionFailed = {}
      var that =
          $(this).data('fileupload') || $(this).data('blueimpJUIFileupload'),
        options = that.options,
        files = data.files
      $(this)
        .fileupload('process', data)
        .done(function () {
          that._adjustMaxNumberOfFiles(-files.length)
          data.isAdjusted = true
          data.files.valid = data.isValidated = that._validate(files)
          data.context = that._renderUpload(files).data('data', data)
          options.filesContainer[options.prependFiles ? 'prepend' : 'append'](
            data.context,
          )
          that._renderPreviews(files, data.context)
          that._forceReflow(data.context)

          that._transition(data.context).done(function () {
            if (
              that._trigger('added', e, data) !== false &&
              (options.autoUpload || data.autoUpload) &&
              data.autoUpload !== false &&
              data.isValidated
            ) {
              data.submit()
            }
          })
        })
    },

    // Callback for the start of each file upload request:
    send: function (e, data) {
      var that =
        $(this).data('fileupload') || $(this).data('blueimpJUIFileupload')
      if (!data.isValidated) {
        if (!data.isAdjusted) {
          that._adjustMaxNumberOfFiles(-data.files.length)
        }
        if (!that._validate(data.files)) {
          return false
        }
      }
      if (
        data.context &&
        data.dataType &&
        data.dataType.substr(0, 6) === 'iframe'
      ) {
        // Iframe Transport does not support progress events.
        // In lack of an indeterminate progress bar, we set
        // the progress to 100%, showing the full animated bar:
        data.context
          .find('.progress')
          .addClass(!$.support.transition && 'progress-animated')
          .attr('aria-valuenow', 100)
          .find('.bar')
          .css('width', '100%')
      }
      return that._trigger('sent', e, data)
    },
    // Callback for successful uploads:
    done: function (e, data) {
      var that =
          $(this).data('fileupload') || $(this).data('blueimpJUIFileupload'),
        template
      if (data.context) {
        data.context.each(function (index) {
          if (
            data instanceof Object &&
            typeof data.result.fileContent == 'string'
          ) {
            var file = data.result
          } else {
            var file = {error: 'emptyResult'}
          }

          if (file.error) {
            that._adjustMaxNumberOfFiles(1)
          }
          that._transition($(this)).done(function () {
            var node = $(this)
            template = that._renderDownload([file]).replaceAll(node)
            that._forceReflow(template)
            that._transition(template).done(function () {
              data.context = $(this)
              that._trigger('completed', e, data)
            })
            $('.size span').css('visibility', 'visible')
            $(data.context)
              .find('.size + td')
              .addClass('file_upload_ok')
              .html('<span>' + file.message + '</span>')
          })
        })
      } else {
        template = that
          ._renderDownload(data.result)
          .appendTo(that.options.filesContainer)
        that._forceReflow(template)
        that._transition(template).done(function () {
          data.context = $(this)
          that._trigger('completed', e, data)
        })
      }
    },
    // Callback for failed (abort or error) uploads:
    fail: function (e, data) {
      conversionFailed = true
      conversionInfo = data.originalFiles[0]
      var that =
          $(this).data('fileupload') || $(this).data('blueimpJUIFileupload'),
        template
      that._adjustMaxNumberOfFiles(data.files.length)
      if (data.context) {
        data.context.each(function (index) {
          var file = {
            fileName: data.originalFiles[0].name,
            size: data.originalFiles[0].size,
            type: data.originalFiles[0].type,
          }
          file.error =
            'An error occurred. Please, be sure that the xliff file has been downloaded from Matecat'
          that._transition($(this)).done(function () {
            var node = $(this)
            template = that._renderDownload([file]).replaceAll(node)
            that._forceReflow(template)
            that._transition(template).done(function () {
              data.context = $(this)
              that._trigger('failed', e, data)
            })
            $('.size span').css('visibility', 'visible')
          })
        })
      } else if (data.errorThrown !== 'abort') {
        that._adjustMaxNumberOfFiles(-data.files.length)
        data.context = that
          ._renderUpload(data.files)
          .appendTo(that.options.filesContainer)
          .data('data', data)
        that._forceReflow(data.context)
        that._transition(data.context).done(function () {
          data.context = $(this)
          that._trigger('failed', e, data)
        })
      } else {
        that._trigger('failed', e, data)
      }
    },
    // Callback for upload progress events:
    progress: function (e, data) {
      if (data.context) {
        var progress = parseInt((data.loaded / data.total) * 100, 10)
        data.context
          .find('.progress')
          .attr('aria-valuenow', progress)
          .find('.bar')
          .css('width', progress + '%')
      }
    },
    // Callback for global upload progress events:
    progressall: function (e, data) {
      var $this = $(this),
        upload =
          $(this).data('fileupload') || $(this).data('blueimpJUIFileupload'),
        progress = parseInt((data.loaded / data.total) * 100, 10),
        globalProgressNode = $this.find('.fileupload-progress'),
        extendedProgressNode = globalProgressNode.find('.progress-extended')
      if (extendedProgressNode.length) {
        extendedProgressNode.html(upload._renderExtendedProgress(data))
      }
      globalProgressNode
        .find('.progress')
        .attr('aria-valuenow', progress)
        .find('.bar')
        .css('width', progress + '%')
    },
    // Callback for uploads start, equivalent to the global ajaxStart event:
    start: function (e) {
      var that =
        $(this).data('fileupload') || $(this).data('blueimpJUIFileupload')
      that._transition($(this).find('.fileupload-progress')).done(function () {
        that._trigger('started', e)
      })
    },
    // Callback for uploads stop, equivalent to the global ajaxStop event:
    stop: function (e) {
      var that =
        $(this).data('fileupload') || $(this).data('blueimpJUIFileupload')
      that._transition($(this).find('.fileupload-progress')).done(function () {
        $(this)
          .find('.progress')
          .attr('aria-valuenow', '0')
          .find('.bar')
          .css('width', '0%')
        $(this).find('.progress-extended').html('&nbsp;')
        that._trigger('stopped', e)
      })
    },
    // Callback for file deletion:
    destroy: function (e, data) {
      var that =
        $(this).data('fileupload') || $(this).data('blueimpJUIFileupload')
      if (data.url) {
        $.ajax(data)
        that._adjustMaxNumberOfFiles(1)
      }

      var _deleteRow = function (rowToBeDeleted) {
        that._transition($(rowToBeDeleted)).done(function () {
          $(this).remove()
          that._trigger('destroyed', e, data)
        })
      }

      _deleteRow(data.context)

      /* END Editing */
    },
    complete: function (e, data) {
      if (
        conversionFailed == false ||
        JSON.stringify(conversionFailed) === JSON.stringify({})
      ) {
        console.log('completed!!!')
        var result = e.responseJSON //JSON.parse( data.result );
        saveAs(b64toBlob(result.fileContent), result.fileName)
      }
    },
  })
  $._data($('#fileupload')[0], 'events')['fileuploadcompleted'] = []

  UI.errorsBeforeUpload = function (file) {
    console.log(file)

    var msg = ''

    if (file.type.match(/^image/)) {
      msg = 'Images not allowed in Matecat'
    } else {
      msg = 'Filetype not allowed in this page. Please upload an xliff file'
    }

    UI.checkFailedConversionsNumber()

    console.log('msg: ', msg)

    return msg
  }
})
