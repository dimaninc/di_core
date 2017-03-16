class diConfiguration
    constructor: ->
        @initTabs().initUploadedPics()

    initTabs: ->
        new diTabs
            $tabsContainer: $ '.diadminform_tabs ul'
            $pagesContainer: $ 'form [data-purpose="tab-pages"]'

        $ 'form button[data-purpose="cancel"]'
        .click ->
            if confirm 'All unsaved data will be lost. Are you sure?'
                window.location.reload()
            false

        $ '.configuration form .grid .file-info a'
        .on 'click', ->
            confirm 'Вы уверены?'

        @

    initUploadedPics: ->
        $ '.configuration .grid .uploaded-pic img'
        .on 'click', ->
            $ @
            .parent().toggleClass 'zoomed'
        @