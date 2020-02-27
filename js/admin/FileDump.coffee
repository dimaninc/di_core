class FileDump
    constructor: (opts) ->
        @opts = $.extend
            workerUri: null
        , opts
        @setupCreateButton()

    setupCreateButton: ->
        $ '[data-action="create.files"]'
        .on 'click', =>
            @log 'Creating files dump...'
            $.post @opts.workerUri, {}, (res) =>
                if res.ok
                    @log 'Files dump created'
                else
                    @log 'Error: ' + res.message
            false
        @

    log: (message) ->
        A.console.add message
        @

    error: (message) ->
        alert message
        @
