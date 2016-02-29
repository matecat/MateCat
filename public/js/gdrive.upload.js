APP.tryListGDriveFiles = function() {
    $.getJSON('/webhooks/gdrive/list', function(listFiles){
        if(listFiles && listFiles.hasOwnProperty('fileName')){
            //TODO: Iterate when multiple files are enabled
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
                        'class': 'extsli'
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
                        'data-type': '',
                        'data-url': 'http://matecat.dev:8080/lib/Utils/fileupload/?file=' + listFiles.fileName + '&_method=DELETE',
                        'role': 'button',
                        'aria-disabled': 'false'
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

APP.formatBytes = function(bytes,decimals) {
   if(bytes === 0) return '0 Byte';
   var k = 1024;
   var dm = decimals + 1 || 2;
   var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
   var i = Math.floor(Math.log(bytes) / Math.log(k));
   return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
};