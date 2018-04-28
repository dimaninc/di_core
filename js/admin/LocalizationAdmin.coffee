class LocalizationAdmin
    constructor: ->
        @setupForm()
        .setupList()

    setupForm: ->
        @setupAutoHeight()

    setupList: ->
        @setupExport()

    setupAutoHeight: ->
        $ '.diadminform-row'
        .filter '[data-field$="value"]'
        .find 'textarea'
        .autoHeight()
        @

    setupExport: ->
        $ '.filter-block [name="export"]'
        .on 'click', ->
            $t = $ '.dinicetable'
            $cb = $t.find 'tr td.id .checked, tr td.id input:checkbox:checked'
            linesAr = []
            $out = $ '.export-block'

            if $out.length and $out.is ':visible'
                $out.hide()
                return false

            unless $cb.length
                alert 'Выберите хотя бы один Токен'
                return false

            $cb.each ->
                valuesAr = []
                $td = $(@).parent()
                while $td = $td.next 'td:eq(0)'
                    break if $td.hasClass 'btn'
                    val = $td.find('[data-purpose="orig"]').html()
                    val = $td.html() if val is undefined or val is null
                    val = val.replace(/'/g, '\\\'').replace(/"/g, '\\\"')
                    valuesAr.push val
                s = '$this->getDb()->q("INSERT IGNORE INTO `' + $t.data('table') + '`(`name`,`value`,`en_value`,`de_value`,`it_value`,`es_value`,`fr_value`)\n' + '\u0009\u0009\u0009VALUES(\'' + valuesAr.join('\',\'') + '\');' + '");'
                linesAr.push s
                true

            unless $out.length
                $out = $ '<div class="export-block"><textarea></textarea></div>'
                .insertAfter $(@).parent()

            $out.show().find('textarea').val linesAr.join '\n'

            false
        @
