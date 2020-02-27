class FileDump
    constructor: (opts) ->
        @opts = $.extend
            workerUri: null
        , opts
        @readVocabulary()
        .setupDumpButtons()

    readVocabulary: ->
        @wrapper = $ '.dump-wrapper'
        @local = @wrapper.data 'vocabulary'
        @

    l: (key) -> @local[key]

    setupDumpButtons: ->
        self = @

        $('[data-action="create.files"]').on 'click', => @createDump()

        $(document.body)
        .on 'click', '[data-type="file"] button[data-action="restore"]', ->
            self.restoreDump $(this).closest('[data-filename]').data('filename')
        .on 'click', '[data-type="file"] button[data-action="download"]', ->
            self.downloadDump $(this).closest('[data-filename]').data('filename')
        .on 'click', '[data-type="file"] button[data-action="delete"]', ->
            self.deleteDump $(this).closest('[data-filename]').data('filename')
        @

    addDumpRow: (res) ->
        html = '<tr data-filename="{0}">'.format(res.file) +
            '<td><b>' + res.file + '</b> ' +
            '<small>(' + (new Date().toLocaleString()) + ' , <span data-field="size">⏳</span>)</small></td>' +
            '<td>' +
            #'<button type="button" data-action="restore">{0}</button> '.format(@l 'dump.restore') +
            '<button type="button" data-action="download">{0}</button> '.format(@l 'dump.download') +
            '<button type="button" data-action="delete">{0}</button>'.format(@l 'dump.delete') +
            '</td></tr>'

        $('.db-dump-rows[data-type="file"] tbody tr:first-child').after html

        $size = $('.db-dump-rows[data-type="file"] tr[data-filename="{0}"] [data-field="size"]'.format(res.file))
        prev_size = 0

        interval = setInterval =>
            $.get @opts.workerUri + 'update_size/', { file: res.file }, (res) =>
                return unless interval
                $size
                .addClass 'in-progress'
                .html size_in_bytes res.size
                if prev_size is res.size
                    clearInterval(interval)
                    $size.removeClass 'in-progress'
                prev_size = res.size
        , 1000
        @

    createDump: ->
        @log 'Creating files dump...'
        @worker 'create'
        @

    restoreDump: (file, folderId) ->
        return @ unless confirm "Are your sure you want to restore this file dump?\nWarning! Current files might be deleted or damaged!"

        @log 'Restoring file dump ' + file + '...'
        @worker 'restore',
            file: file
            folderId: folderId
        @

    downloadDump: (file, folderId) ->
        @log 'Downloading file dump ' + file + '...'
        @worker 'download',
            file: file
            folderId: folderId
        @

    deleteDump: (file) ->
        return @ unless confirm 'Are you sure you want to delete the file dump ' + file + '?'

        @log 'Deleting file dump ' + file + '...'
        @worker 'delete',
            file: file
            folderId: 1
        @

    worker: (action, options) ->
        options = file: options if typeof options isnt 'object'

        options = $.extend
            file: ''
            folderId: 0
        , options

        urlBase = @opts.workerUri + action + '/'

        if action is 'download'
            options.headers = 1
            window.location.href = urlBase + '?' + $.param(options)
            return false

        $.get urlBase, options, (res) =>
            unless res.ok
                @log 'Unable to complete action `' + action + '` with file `' + options.file + '`'
                @log 'Error: ' + res.message
                return false

            if action is 'create'
                @log 'Dump has been created'
                @addDumpRow res
            else if action is 'restore'
                @log 'Dump ' + options.file + ' has been successfully restored'
            else if action is 'delete'
                @log res.file + ' has been deleted'
                $('.db-dump-rows[data-type="file"] tbody tr[data-filename="' + res.file + '"]').fadeOut()
            else
                @log 'Unknown action "' + action + '"'

        @

    log: (message) ->
        A.console.add message
        @

    error: (message) ->
        alert message
        @
