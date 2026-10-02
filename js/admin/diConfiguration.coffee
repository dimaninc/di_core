class diConfiguration
    constructor: ->
        @initTabs().initUploadedPics()

    initTabs: ->
        tabs = new diTabs
            $tabsContainer: $ '.diadminform_tabs ul'
            $pagesContainer: $ 'form [data-purpose="tab-pages"]'

        # Admin\Page\Configuration::printEditLogTab(): present only when
        # shouldLazyLoadEditLog() is true, same container/endpoint diAdminForm.js
        # uses for a record's own log tab.
        diEditLogLazyLoad tabs, 'admin_edit_log'

        $ 'form button[data-purpose="cancel"]'
        .click ->
            if confirm 'All unsaved data will be lost. Are you sure?'
                window.location.reload()
            false

        $ '.configuration form .grid .file-info a[data-purpose="del"]'
        .on 'click', ->
            confirm 'Вы уверены?'

        @

    initUploadedPics: ->
        $ '.configuration .grid .uploaded-pic img'
        .on 'click', ->
            $ @
            .parent().toggleClass 'zoomed'
        @
