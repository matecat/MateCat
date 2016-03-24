APP.tryListGDriveFiles = function() {
    $.getJSON('/webhooks/gdrive/list', function(listFiles){
        if(listFiles && listFiles.hasOwnProperty('fileName')){
            //TODO: Iterate when multiple files are enabled
            var iconClass = '';

            if ( listFiles.fileExtension == 'docx' ) {
                iconClass = 'extgdoc';
            } else if ( listFiles.fileExtension == 'pptx' ) {
                iconClass = 'extgsli';
            } else if ( listFiles.fileExtension == 'xlsx' ) {
                iconClass = 'extgsheet';
            }

            $('.files-gdrive').html('');

            $('<tr/>', {
                'class': 'template-gdrive fade ready',
                'style': 'display: table-row;'
            })
            .append (
                $('<td/>', {
                    'class': 'preview'
                })
                .append (
                    $('<span/>', {
                        'class': iconClass
                    })
                )
            )
            .append (
                $('<td/>', {
                    'class': 'name',
                    text: listFiles.fileName
                })
            )
            .append (
                $('<td/>', {
                    'class': 'size'
                })
                .append (
                    $('<span/>', {
                        text: APP.formatBytes(listFiles.fileSize)
                    })
                )
            )
            .append (
                $('<td/>', {
                    'class': 'delete'
                })
                .append (
                    $('<button/>', {
                        'class': 'btn btn-dange ui-button ui-widget ui-state-default ui-corner-all ui-button-text-icon-primary',
                        'data-file': listFiles.fileName,
                        'role': 'button',
                        'aria-disabled': 'false',
                        click: function() {
                            APP.deleteGDriveFile( $(this).data('file') );
                        }
                    })
                    .append (
                        $('<span/>', {
                            'class': 'ui-button-icon-primary ui-icon ui-icon-trash'
                        })
                    )
                    .append (
                        $('<span/>', {
                            'class': 'ui-button-text'
                        })
                        .append (
                            $('<i/>', {
                                'class': 'icon-ban-circle icon-white'
                            })
                        )
                        .append (
                            $('<span/>', {
                                text: 'Delete'
                            })
                        )
                    )
                )
            )
            .appendTo('.files-gdrive');
        }
    });
};

APP.restartGDriveConversions = function () {
    var sourceLang = $("#source-lang").val();
    
    $.getJSON('/webhooks/gdrive/change/' + sourceLang, function(response){
        if(response.success) {
            console.log('Source language changed.');
        }
    });
};

APP.deleteGDriveFile = function (fileName) {
    $.getJSON('/webhooks/gdrive/delete/' + fileName, function(response){
        if(response.success) {
            window.open('/', '_self');
        }
    });
};

APP.formatBytes = function(bytes,decimals) {
   if(bytes === 0) return '0 Byte';
   var k = 1024;
   var dm = decimals + 1 || 2;
   var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
   var i = Math.floor(Math.log(bytes) / Math.log(k));
   return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};